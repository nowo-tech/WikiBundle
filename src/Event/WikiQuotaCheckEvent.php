<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Error;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Port\WikiSnapshots;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

use function mb_scrub;
use function mb_substr;
use function preg_replace;
use function property_exists;
use function sprintf;
use function trigger_deprecation;
use function trigger_error;
use function trim;

use const E_USER_WARNING;

/**
 * Dispatched before the wiki writes something a host may meter (1.21, {@see WikiEvents::QUOTA_CHECK}): a listener
 * calls {@see self::deny()} to refuse it (plan limits, storage quotas) and nothing is written. Dispatched after the
 * access checks and the CSRF check, so a listener only sees requests the user may make. Without a listener nothing
 * changes.
 *
 * | action | when | count | bytes |
 * |---|---|---|---|
 * | `page_create` | a new page (form, or a copy of a version) | 1 | 0 |
 * | `page_unarchive` | an archived page comes back | 1 | 0 |
 * | `trash_restore` | a deletion comes back from the trash | pages of the deletion | 0 |
 * | `import` | an import archive | Markdown documents of the archive (an upper bound of the new pages) | archive size |
 * | `attachment_upload` | a file attached to a page | 1 | file size |
 *
 * The refusal: the page form answers 422 with the reason as an error flash, unarchive / restore / import redirect with an
 * error flash, an upload answers 422 `{"error": "quota_exceeded", "reason": "…"}`. The reason is plain text (escaped
 * wherever it is shown); an empty one becomes the bundle's `wiki.quota.denied`.
 *
 * @property UserInterface $user the user who acts
 *                               (deprecated since 1.36, a runtime notice since 1.49, removed in 2.0): read
 *                               {@see self::userRef()}
 */
final class WikiQuotaCheckEvent extends Event
{
    public const PAGE_CREATE = 'page_create';

    public const PAGE_UNARCHIVE = 'page_unarchive';

    public const TRASH_RESTORE = 'trash_restore';

    public const IMPORT = 'import';

    public const ATTACHMENT_UPLOAD = 'attachment_upload';

    /** Longest reason kept (a flash or a JSON field, never a page). */
    public const MAX_REASON = 500;

    private ?string $reason = null;

    /** The user who acts ({@see self::securityUser()}; `$event->user` is deprecated). */
    private readonly UserInterface $securityUser;

    /**
     * @param string $action one of the action constants
     * @param WikiSpace $space space written to
     * @param UserInterface $user user who acts; deprecated since 1.36 as a public member (a Symfony type), removed in
     *                            2.0: read {@see self::userRef()}
     * @param int $count pages (or files) the write adds
     * @param int $bytes bytes the write adds (upload, import archive)
     * @param WikiPage|null $page page concerned (unarchive, attachment, the copied page), null for a new page or an import
     */
    public function __construct(
        public readonly string $action,
        public readonly WikiSpace $space,
        UserInterface $user,
        public readonly int $count = 1,
        public readonly int $bytes = 0,
        public readonly ?WikiPage $page = null,
    ) {
        $this->securityUser = $user;
    }

    /**
     * Refuses the write and stops the other listeners.
     *
     * @param string $reason text shown to the user (plain text; '' = the bundle's default)
     */
    public function deny(string $reason = ''): void
    {
        // Audit GV-I3 / I4 / I8: invalid UTF-8 is scrubbed so the text survives; controls and line separators become a
        // space; format characters (bidi overrides, zero-width) go, except the joiners that emoji and Persian text need.
        $text = preg_replace(['/[\p{Cc}\p{Zl}\p{Zp}]+/u', '/[^\P{Cf}\x{200C}\x{200D}]+/u'], [' ', ''], mb_scrub($reason, 'UTF-8'));
        // Joiners alone are no text: such a reason falls to the default, like any other format-only one.
        $text = trim((string) $text);
        // @igor-ignore - Request-scoped event, created per dispatch and excluded from the services; no worker state.
        $this->reason = mb_substr(trim($text, "\u{200C}\u{200D} ") === '' ? '' : $text, 0, self::MAX_REASON);
        $this->stopPropagation();
    }

    public function isDenied(): bool
    {
        return $this->reason !== null;
    }

    /** The reason given to {@see self::deny()} (control and format characters removed, at most 500 characters), else null. */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * 1.36: the space's id.
     */
    public function spaceId(): string
    {
        return $this->space->getId();
    }

    /**
     * 1.36: the space as a framework-free snapshot.
     */
    public function spaceSnapshot(): WikiSpaceSnapshot
    {
        return WikiSnapshots::space($this->space);
    }

    /**
     * 1.36: the page's id; null for a new page or an import.
     */
    public function pageId(): ?string
    {
        return $this->page?->getId();
    }

    /**
     * 1.36: the page as a snapshot; null for a new page or an import.
     */
    public function pageSnapshot(): ?WikiPageSnapshot
    {
        return WikiSnapshots::pageOrNull($this->page);
    }

    /**
     * 1.36: the user who acts, as a framework-free reference (use it instead of {@see self::$user}).
     */
    public function userRef(): WikiUserRef
    {
        return WikiUserRefs::fromUser($this->securityUser);
    }

    /**
     * 1.49: `$event->user` still works and triggers a deprecation notice; 2.0 removes it.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'user') {
            trigger_deprecation('nowo-tech/wiki-bundle', '1.49', 'Reading %s::$user (a Symfony user) is deprecated, 2.0 removes it: read userRef() (a framework-free WikiUserRef) instead.', self::class);

            return $this->securityUser;
        }
        if (property_exists($this, $name)) {
            // A private member, refused from outside as before.
            throw new Error(sprintf('Cannot access private property %s::$%s', self::class, $name));
        }
        trigger_error(sprintf('Undefined property: %s::$%s', self::class, $name), E_USER_WARNING);

        return null;
    }

    public function __isset(string $name): bool
    {
        return $name === 'user';
    }

    /** `user` stays read-only, as it was (a readonly property up to 1.48). */
    public function __set(string $name, mixed $value): void
    {
        if ($name === 'user') {
            throw new Error(sprintf('Cannot modify readonly property %s::$user', self::class));
        }
        if (property_exists($this, $name)) {
            // A private member, refused from outside as before.
            throw new Error(sprintf('Cannot modify private property %s::$%s', self::class, $name));
        }
        // @igor-ignore - a per-dispatch event object (never a shared service): a dynamic property, as PHP allowed before 1.49
        $this->{$name} = $value;
    }

    /**
     * The user as the bundle works with it. Bundle use only: removed in 2.0 with {@see self::$user}.
     *
     * @internal
     */
    public function securityUser(): UserInterface
    {
        return $this->securityUser;
    }
}
