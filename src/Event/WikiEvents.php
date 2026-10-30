<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Event;

use Nowo\WikiBundle\Service\WikiPageArchivedEvent;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;

/**
 * Wiki bundle event names. Every event is dispatched after the change is flushed.
 */
final class WikiEvents
{
    /** {@see WikiPageSavedEvent}: a revision was saved (also after create and restore). */
    public const PAGE_SAVED = 'nowo_wiki.page.saved';

    /** {@see WikiPageArchivedEvent}. */
    public const PAGE_ARCHIVED = 'nowo_wiki.page.archived';

    /** {@see WikiPageCreatedEvent} (1.9). */
    public const PAGE_CREATED = 'nowo_wiki.page.created';

    /** {@see WikiPageUnarchivedEvent} (1.9). */
    public const PAGE_UNARCHIVED = 'nowo_wiki.page.unarchived';

    /** {@see WikiPageRestoredEvent} (1.9). */
    public const PAGE_RESTORED = 'nowo_wiki.page.restored';

    /** {@see WikiPageLinkEvent}: a page was linked to a host object (1.9). */
    public const PAGE_LINKED = 'nowo_wiki.page.linked';

    /** {@see WikiPageLinkEvent}: a page was unlinked from a host object (1.9). */
    public const PAGE_UNLINKED = 'nowo_wiki.page.unlinked';

    /** {@see WikiAttachmentEvent}: an attachment was uploaded (1.9). */
    public const ATTACHMENT_UPLOADED = 'nowo_wiki.attachment.uploaded';

    /** {@see WikiEditorOptionsEvent}: before the page editor is built (1.9). */
    public const EDITOR_OPTIONS = 'nowo_wiki.editor.options';

    /** {@see WikiAttachmentEvent}: an attachment was deleted (1.9). */
    public const ATTACHMENT_DELETED = 'nowo_wiki.attachment.deleted';
}
