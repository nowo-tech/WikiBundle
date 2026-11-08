<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Closure;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Api\Hook\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRateLimiterInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiUsersInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiManagementInterface;
use Nowo\WikiBundle\Api\Value\WikiApiNewWiki;
use Nowo\WikiBundle\Api\Value\WikiApiRateLimitState;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\Value\WikiApiWikiFilter;
use Nowo\WikiBundle\Api\Value\WikiApiWikiPatch;
use Nowo\WikiBundle\Api\WikiApiAuthFailure;
use Nowo\WikiBundle\Api\WikiApiCredential;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_values;
use function in_array;
use function is_array;
use function preg_replace;
use function strtolower;

/**
 * The host side of the API tests, scripted by each test through static state (the kernel boots a fresh container per
 * request): tokens, wikis and their flags, the person's grants, rate limits and the plan.
 */
final class ApiTestHost implements WikiApiAuthenticatorInterface, WikiApiDirectoryInterface, WikiApiWikiManagementInterface, WikiApiRateLimiterInterface, WikiApiPlanGateInterface, WikiApiUsersInterface, WikiSpaceAccessCheckerInterface
{
    /** @var array<string, array{user: string, level: WikiApiLevel|null, where: WikiApiWhere, name?: string}|string> secret => token, or 'disabled' */
    public static array $tokens = [];

    /** @var array<string, array{name?: string, restricted?: bool, archived?: bool, can_open?: bool, owner?: string, unopened?: bool}> slug => flags */
    public static array $wikis = [];

    /** @var array<string, array<string, list<WikiAction>>> user id => space slug => granted actions */
    public static array $grants = [];

    /** @var list<string> page slugs nobody may view */
    public static array $hiddenPages = [];

    /** @var list<string> wiki slugs whose plan has no wiki module */
    public static array $noPlan = [];

    /** A write raced by someone else: run when the person's Edit or Restore right is asked, until it returns true. */
    public static ?Closure $race = null;

    public static bool $rateLimited = false;

    public static bool $ipBlocked = false;

    /** @var list<string> */
    public static array $failedIps = [];

    /** @var list<string> slugs provisioned */
    public static array $provisioned = [];

    public function __construct(private readonly ManagerRegistry $doctrine)
    {
    }

    public static function reset(): void
    {
        self::$tokens         = [];
        self::$wikis          = [];
        self::$grants         = [];
        self::$hiddenPages    = [];
        self::$noPlan         = [];
        self::$rateLimited    = false;
        self::$ipBlocked      = false;
        self::$failedIps      = [];
        self::$provisioned    = [];
        self::$race           = null;
        ApiTestFiles::$stored = [];
    }

    public function authenticate(Request $request): WikiApiPrincipal|WikiApiAuthFailure
    {
        $token = self::$tokens[(string) WikiApiCredential::fromRequest($request)] ?? null;
        if ($token === 'disabled') {
            return WikiApiAuthFailure::ApiDisabled;
        }
        if ($token === null || $token === 'invalid') {
            return WikiApiAuthFailure::Invalid;
        }
        if (!$token['level'] instanceof WikiApiLevel) {
            return WikiApiAuthFailure::NoWikiLevel;
        }
        $user = $this->doctrine->getManager()->find(TestUserEntity::class, $token['user']);

        return $user instanceof TestUserEntity
            ? new WikiApiPrincipal($user, $token['level'], $token['where'], 'tok-' . $token['user'], $token['name'] ?? 'test token')
            : WikiApiAuthFailure::Invalid;
    }

    /** The user of a secret, for the test firewall. */
    public function userOf(string $secret): ?UserInterface
    {
        $token = self::$tokens[$secret] ?? null;
        if (!is_array($token)) {
            return null;
        }
        $user = $this->doctrine->getManager()->find(TestUserEntity::class, $token['user']);

        return $user instanceof TestUserEntity ? $user : null;
    }

    public function list(WikiApiPrincipal $principal, WikiApiWikiFilter $filter): array
    {
        $wikis = [];
        foreach (self::$wikis as $slug => $flags) {
            $wiki = $this->find($principal, $slug);
            if ($wiki instanceof WikiApiWiki && ($filter->archived === null || $filter->archived === $wiki->archived) && ($filter->ownerId === null || $filter->ownerId === $wiki->ownerId)) {
                $wikis[] = $wiki;
            }
        }

        return $wikis;
    }

    public function find(WikiApiPrincipal $principal, string $slug): ?WikiApiWiki
    {
        $flags = self::$wikis[$slug] ?? null;
        if ($flags === null) {
            return null;
        }
        $space = ($flags['unopened'] ?? false) ? null : $this->doctrine->getManager()->getRepository(WikiSpace::class)->findOneBy(['slug' => $slug]);

        return new WikiApiWiki(
            $slug,
            $space instanceof WikiSpace ? $space : null,
            $flags['name'] ?? $slug,
            'About ' . $slug,
            'project',
            $flags['owner'] ?? 'owner-1',
            $flags['owner'] ?? 'owner-1',
            $flags['archived'] ?? false,
            $flags['restricted'] ?? false,
            $flags['can_open'] ?? true,
            $flags['can_open'] ?? true ? 3 : null,
        );
    }

    public function ofSpace(WikiApiPrincipal $principal, WikiSpace $space): ?WikiApiWiki
    {
        return $this->find($principal, $space->getSlug());
    }

    public function provision(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiSpace
    {
        $manager = $this->doctrine->getManager();
        $space   = new WikiSpace($wiki->slug, $wiki->name, WikiSpaceOwnerScope::Team, $wiki->ownerKey ?? 'owner-1');
        $manager->persist($space);
        $manager->flush();
        self::$provisioned[]                  = $wiki->slug;
        self::$wikis[$wiki->slug]['unopened'] = false;

        return $space;
    }

    public function create(WikiApiPrincipal $principal, WikiApiNewWiki $wiki): WikiApiWiki
    {
        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $wiki->name));
        if (isset(self::$wikis[$slug])) {
            throw new WikiApiRefusal(409, 'WIKI_003', 'This name is taken');
        }
        self::$wikis[$slug] = ['name' => $wiki->name, 'owner' => $wiki->ownerId, 'unopened' => true];

        return $this->find($principal, $slug) ?? throw new WikiApiRefusal(500, 'SERVER_001', 'Server error');
    }

    public function update(WikiApiPrincipal $principal, WikiApiWiki $wiki, WikiApiWikiPatch $patch): WikiApiWiki
    {
        self::$wikis[$wiki->slug]['name'] = $patch->name ?? $wiki->name;

        return $this->find($principal, $wiki->slug) ?? $wiki;
    }

    public function archive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki
    {
        self::$wikis[$wiki->slug]['archived'] = true;

        return $this->find($principal, $wiki->slug) ?? $wiki;
    }

    public function unarchive(WikiApiPrincipal $principal, WikiApiWiki $wiki): WikiApiWiki
    {
        self::$wikis[$wiki->slug]['archived'] = false;

        return $this->find($principal, $wiki->slug) ?? $wiki;
    }

    public function consume(WikiApiPrincipal $principal): WikiApiRateLimitState
    {
        return new WikiApiRateLimitState(!self::$rateLimited, 100, self::$rateLimited ? 0 : 99, 1_800_000_000);
    }

    public function failedTokenState(string $clientIp): WikiApiRateLimitState
    {
        return new WikiApiRateLimitState(!self::$ipBlocked, 20, 20, 1_800_000_000);
    }

    public function registerFailedToken(string $clientIp): void
    {
        self::$failedIps[] = $clientIp;
    }

    public function allowsWiki(UserInterface $user, WikiApiWiki $wiki): bool
    {
        return !in_array($wiki->slug, self::$noPlan, true);
    }

    public function apiId(object $user): ?string
    {
        return $user instanceof TestUserEntity ? 'api-' . $user->getUserIdentifier() : null;
    }

    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
        if (self::$race !== null && $page !== null && ($action === WikiAction::Edit || $action === WikiAction::Restore)) {
            $race       = self::$race;
            self::$race = null;
            if ($race($page) === false) {
                self::$race = $race;
            }
        }
        if ($page instanceof WikiPage && in_array($page->getSlug(), self::$hiddenPages, true)) {
            return false;
        }

        return in_array($action, self::$grants[$user->getUserIdentifier()][$space->getSlug()] ?? [], true);
    }

    /** Every action but the ones the API never asks. @return list<WikiAction> */
    public static function all(): array
    {
        return array_values(WikiAction::cases());
    }
}
