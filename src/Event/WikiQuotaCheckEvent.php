<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

use function mb_substr;
use function preg_replace;
use function trim;

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
 * | `import` | an import archive (pages are unknown before it is read: at least one) | 1 | archive size |
 * | `attachment_upload` | a file attached to a page | 1 | file size |
 *
 * The refusal: the page form shows the reason as a form error (422), unarchive / restore / import redirect with an
 * error flash, an upload answers 422 `{"error": "quota_exceeded", "reason": "…"}`. The reason is plain text (escaped
 * wherever it is shown); an empty one becomes the bundle's `wiki.quota.denied`.
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

    /**
     * @param string $action one of the action constants
     * @param WikiSpace $space space written to
     * @param UserInterface $user user who acts
     * @param int $count pages (or files) the write adds
     * @param int $bytes bytes the write adds (upload, import archive)
     * @param WikiPage|null $page page concerned (unarchive, attachment, the copied page), null for a new page or an import
     */
    public function __construct(
        public readonly string $action,
        public readonly WikiSpace $space,
        public readonly UserInterface $user,
        public readonly int $count = 1,
        public readonly int $bytes = 0,
        public readonly ?WikiPage $page = null,
    ) {
    }

    /**
     * Refuses the write and stops the other listeners.
     *
     * @param string $reason text shown to the user (plain text; '' = the bundle's default)
     */
    public function deny(string $reason = ''): void
    {
        // @igor-ignore - Request-scoped event, created per dispatch and excluded from the services; no worker state.
        $this->reason = mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $reason)), 0, self::MAX_REASON);
        $this->stopPropagation();
    }

    public function isDenied(): bool
    {
        return $this->reason !== null;
    }

    /** The reason given to {@see self::deny()} (control characters removed, at most 500 characters), else null. */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
