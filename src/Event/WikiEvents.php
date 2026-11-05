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

    /** {@see WikiPageMovedEvent}: a page was moved in the page tree (1.14). */
    public const PAGE_MOVED = 'nowo_wiki.page.moved';

    /** {@see WikiTrashEvent}: a page and its sub-pages were moved to the trash (1.14). */
    public const PAGE_TRASHED = 'nowo_wiki.page.trashed';

    /** {@see WikiTrashEvent}: a deletion was restored from the trash (1.14). */
    public const TRASH_RESTORED = 'nowo_wiki.trash.restored';

    /** {@see WikiTrashEvent}: a deletion was purged for good (1.14; by a user or the retention). */
    public const TRASH_PURGED = 'nowo_wiki.trash.purged';

    /** {@see WikiAttachmentEvent}: an attachment was uploaded (1.9). */
    public const ATTACHMENT_UPLOADED = 'nowo_wiki.attachment.uploaded';

    /** {@see WikiEditorOptionsEvent}: before the page editor is built (1.9). */
    public const EDITOR_OPTIONS = 'nowo_wiki.editor.options';

    /** {@see WikiAttachmentEvent}: an attachment was deleted (1.9). */
    public const ATTACHMENT_DELETED = 'nowo_wiki.attachment.deleted';

    /** 1.16: a thread was started (inline or page-level). {@see WikiCommentEvent} */
    public const COMMENT_CREATED = 'nowo_wiki.comment.created';

    /** 1.16: a reply was added to a thread. {@see WikiCommentEvent} */
    public const COMMENT_REPLIED = 'nowo_wiki.comment.replied';

    /** 1.16: a comment body was edited. {@see WikiCommentEvent} */
    public const COMMENT_EDITED = 'nowo_wiki.comment.edited';

    /** 1.16: a comment (or its whole thread) was deleted. {@see WikiCommentEvent} */
    public const COMMENT_DELETED = 'nowo_wiki.comment.deleted';

    /** 1.16: a thread was resolved. {@see WikiCommentEvent} */
    public const COMMENT_RESOLVED = 'nowo_wiki.comment.resolved';

    /** 1.16: a resolved thread was reopened. {@see WikiCommentEvent} */
    public const COMMENT_REOPENED = 'nowo_wiki.comment.reopened';

    /** 1.16.1: a moderator deleted a whole thread. {@see WikiCommentEvent} (comment null) */
    public const COMMENT_THREAD_DELETED = 'nowo_wiki.comment.thread_deleted';

    /** 1.16.1: a user's comments were erased or unlinked (privacy). {@see WikiCommentAuthorErasedEvent} */
    public const COMMENT_AUTHOR_ERASED = 'nowo_wiki.comment.author_erased';

    /** 1.17: the mentions of an erased person were rewritten in other people's comments. {@see WikiCommentMentionsErasedEvent} */
    public const COMMENT_MENTIONS_ERASED = 'nowo_wiki.comment.mentions_erased';
}
