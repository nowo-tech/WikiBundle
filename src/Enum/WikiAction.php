<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Enum;

use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;

/**
 * An action asked of {@see WikiSpaceAccessCheckerInterface} on a space (and optionally a page).
 */
enum WikiAction: string
{
    /** Read the space, its pages, the tree and the search within it. */
    case View = 'view';
    /** Create a page in the space. */
    case Create = 'create';
    /** Edit a page (new revision) and upload attachments to it. */
    case Edit = 'edit';
    /** Archive or unarchive a page. */
    case Archive = 'archive';
    /** Read the history and the diff of a page. */
    case History = 'history';
    /** Restore an old revision of a page (appends a new revision). */
    case Restore = 'restore';
    /** Import an Outline / Notion archive into the space. */
    case Import = 'import';
    /** Export the space. */
    case Export = 'export';
    /** Manage the space (settings, delete, any-page archive). */
    case Manage = 'manage';
    /** Ask the AI assistant with the space as context. */
    case AskAi = 'ask_ai';
    /** 1.14 (`trash.enabled`): move a page and its sub-pages to the trash, and restore them. */
    case Delete = 'delete';
    /** 1.14 (`trash.enabled`): delete trashed pages for good. */
    case Purge = 'purge';

    /**
     * Actions asked only when the trash is enabled (1.14): {@see WikiSpaceGuard::grants()} leaves them out, so a host
     * checker written before 1.14 is never asked about them unless the host turns the trash on.
     *
     * @return list<self>
     */
    public static function trashActions(): array
    {
        return [self::Delete, self::Purge];
    }
}
