<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Security;

use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiCapability;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Markdown\WikiRenderReach;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Stringable;

use function is_scalar;
use function method_exists;
use function preg_match;
use function strlen;

/**
 * 1.24 (spec 218): level ∩ person ∩ where, on every request, with no cache across requests. Public, so an in-process
 * caller (spec 219) asks exactly what the HTTP API asks.
 *
 * Order of refusals: out of reach 404 (one body for every reason) → plan 403 `PLAN_001` → archived wiki 403 `WIKI_002`
 * (writes) → level 403 `TOKEN_001` → person 403 `ACCESS_002`.
 */
final readonly class WikiApiGuard
{
    /** A page, file or revision id. */
    public const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

    /** The widest form a wiki slug may take in a route; the exact forms are checked by {@see self::isSlug()}. */
    public const SLUG_ROUTE = '[a-z0-9][a-z0-9-]{0,119}';

    public function __construct(
        private WikiApiDirectoryInterface $directory,
        private WikiApiPlanGateInterface $planGate,
        private WikiSpaceAccessCheckerInterface $checker,
        private WikiAccessCheckerInterface $roles,
        private WikiPageRepositoryInterface $pages,
        private WikiPageRevisionRepositoryInterface $revisions,
        private ?WikiTenantPaths $tenantPaths = null,
    ) {
    }

    /**
     * A wiki the token may see, its plan checked.
     *
     * @throws WikiApiRefusal
     */
    public function wiki(WikiApiPrincipal $principal, string $slug): WikiApiWiki
    {
        $wiki = $this->isSlug($slug) ? $this->directory->find($principal, $slug) : null;
        if (!$wiki instanceof WikiApiWiki || $wiki->slug !== $slug || !$this->sees($principal, $wiki)) {
            throw WikiApiRefusal::notFound();
        }
        if (!$this->planGate->allowsWiki($principal->user, $wiki)) {
            throw WikiApiRefusal::planRefused();
        }

        return $wiki;
    }

    /**
     * Whether a wiki the directory answered may be shown: within *where*, and readable when its space exists.
     */
    public function sees(WikiApiPrincipal $principal, WikiApiWiki $wiki): bool
    {
        if (!$principal->where->reaches($wiki)) {
            return false;
        }

        return !$wiki->space instanceof WikiSpace || !$wiki->canOpen || $this->checker->isGranted($principal->user, WikiAction::View, $wiki->space);
    }

    /**
     * The space of a wiki whose pages are read: 404 for a wiki listed by name only. Null: nobody opened it yet.
     *
     * @throws WikiApiRefusal
     */
    public function openSpace(WikiApiWiki $wiki): ?WikiSpace
    {
        if (!$wiki->canOpen) {
            throw WikiApiRefusal::notFound();
        }

        return $wiki->space;
    }

    /**
     * A page of the wiki the person may read with every ancestor. An archived page only for a level that may archive and
     * a person with `Archive` on it (owner Q-O4), when `$archived` allows it.
     *
     * @throws WikiApiRefusal
     */
    public function page(WikiApiPrincipal $principal, WikiApiWiki $wiki, string $pageId, bool $archived = false): WikiPage
    {
        $space = $this->openSpace($wiki);
        $page  = $space instanceof WikiSpace && preg_match('/^' . self::UUID . '$/D', $pageId) === 1 ? $this->pages->findById($pageId) : null;
        if (!$page instanceof WikiPage || $page->getSpace()->getId() !== $space?->getId()
            || !WikiPageVisibility::isReadable($this->checker, $principal->user, $space, $page)
            || ($page->isArchived() && (!$archived || !$this->maySeeArchived($principal, $space, $page)))) {
            throw WikiApiRefusal::notFound();
        }

        return $page;
    }

    /** Whether archived pages may be read (level >= Edit and `Archive` granted; owner Q-O4). */
    public function maySeeArchived(WikiApiPrincipal $principal, WikiSpace $space, ?WikiPage $page = null): bool
    {
        return WikiApiCapability::allows($principal->level, WikiAction::Archive) && $this->checker->isGranted($principal->user, WikiAction::Archive, $space, $page);
    }

    /**
     * A revision of the page, by number.
     *
     * @throws WikiApiRefusal
     */
    public function revision(WikiPage $page, string $number): WikiPageRevision
    {
        $revision = preg_match('/^[1-9]\d{0,8}$/D', $number) === 1 ? $this->revisions->findByPageAndNumber($page, (int) $number) : null;
        if (!$revision instanceof WikiPageRevision || $revision->getPage()->getId() !== $page->getId()) {
            throw WikiApiRefusal::notFound();
        }

        return $revision;
    }

    /**
     * Level, then the person: `$action` on the space (and page), with the role gate the UI asks for it.
     *
     * @throws WikiApiRefusal
     */
    public function require(WikiApiPrincipal $principal, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): void
    {
        if (!WikiApiCapability::allows($principal->level, $action)) {
            throw WikiApiRefusal::levelRefused();
        }
        if (!$this->roleAllows($principal, $action) || !$this->checker->isGranted($principal->user, $action, $space, $page)) {
            throw WikiApiRefusal::permissionRefused();
        }
    }

    /**
     * A write on a wiki: refused when it is archived (FR-011), before the level and the person.
     *
     * @throws WikiApiRefusal
     */
    public function requireWritable(WikiApiWiki $wiki): void
    {
        if ($wiki->archived) {
            throw WikiApiRefusal::archivedWiki();
        }
    }

    /**
     * At Edit, only what the user created: a page whose first revision is theirs (contract F7, owner Q-O2). Full acts on
     * anyone's, within the checker.
     *
     * @throws WikiApiRefusal
     */
    public function requireOwnPage(WikiApiPrincipal $principal, WikiPage $page): void
    {
        if (WikiApiCapability::actsOnAnyone($principal->level)) {
            return;
        }
        $first = $this->revisions->findByPageAndNumber($page, 1);
        if (!$first instanceof WikiPageRevision || !$this->isUser($principal, $first->getAuthor())) {
            throw WikiApiRefusal::levelRefused();
        }
    }

    /** Whether the person behind the token is this user object (same object or same id). */
    public function isUser(WikiApiPrincipal $principal, ?object $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user === $principal->user) {
            return true;
        }
        $mine = $this->idOf($principal->user);

        return $mine !== null && $mine === $this->idOf($user);
    }

    /** Whether the token gets the page's source (FR-012): level >= Edit and `Edit` granted on an active page. */
    public function maySeeSource(WikiApiPrincipal $principal, WikiSpace $space, WikiPage $page): bool
    {
        return WikiApiCapability::maySeeSource($principal->level)
            && !$page->isArchived()
            && $this->roles->canEdit($principal->user)
            && $this->checker->isGranted($principal->user, WikiAction::Edit, $space, $page);
    }

    /** The token's reach for a render: a space shows only when its wiki is within *where* (contract F3). */
    public function reach(WikiApiPrincipal $principal): WikiRenderReach
    {
        return new WikiApiRenderReach($this, $this->directory, $principal);
    }

    /** Whether a slug has a form some space route accepts (the directory decides what it names). */
    public function isSlug(string $slug): bool
    {
        if (strlen($slug) > 120) {
            return false;
        }
        foreach ([WikiRouteLoader::SPACE_SLUG, ...$this->slugRequirements()] as $requirement) {
            if (preg_match('#^(?:' . $requirement . ')$#D', $slug) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function slugRequirements(): array
    {
        $requirements = [];
        foreach ($this->tenantPaths?->tenants() ?? [] as $tenant) {
            $requirements[] = $tenant['slug_requirement'];
        }

        return $requirements;
    }

    private function roleAllows(WikiApiPrincipal $principal, WikiAction $action): bool
    {
        $user = $principal->user;

        return match ($action) {
            WikiAction::Create                    => $this->roles->canCreate($user),
            WikiAction::Edit, WikiAction::Restore => $this->roles->canEdit($user),
            WikiAction::History                   => $this->roles->canViewHistory($user),
            WikiAction::Archive                   => $this->roles->canArchive($user),
            default                               => $this->roles->canList($user),
        };
    }

    private function idOf(object $user): ?string
    {
        $id = method_exists($user, 'getId') ? $user->getId() : null;

        return is_scalar($id) || $id instanceof Stringable ? (string) $id : null;
    }
}
