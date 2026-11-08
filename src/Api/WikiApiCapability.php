<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use Nowo\WikiBundle\Enum\WikiAction;

/**
 * 1.24 (spec 218): what each level may ask, a closed table over {@see WikiAction}. The match has no default arm: a new
 * action does not compile into an answer until it is decided here (the rule the host checker follows too).
 */
final class WikiApiCapability
{
    public static function allows(WikiApiLevel $level, WikiAction $action): bool
    {
        return match ($action) {
            WikiAction::View, WikiAction::History, WikiAction::ViewComments                                            => true,
            WikiAction::Create, WikiAction::Edit, WikiAction::Comment, WikiAction::ResolveComment, WikiAction::Archive => $level !== WikiApiLevel::Read,
            WikiAction::Restore, WikiAction::ModerateComments, WikiAction::Manage                                      => $level === WikiApiLevel::Full,
            // Never through the API, at any level.
            WikiAction::Import, WikiAction::Export, WikiAction::AskAi, WikiAction::Delete, WikiAction::Purge => false,
        };
    }

    /** Whether the level may act on what others created (archive any page, moderate): Full only (contract F7). */
    public static function actsOnAnyone(WikiApiLevel $level): bool
    {
        return $level === WikiApiLevel::Full;
    }

    /** Whether the level may ever get a page's source (FR-012); the person must also be allowed to edit the page. */
    public static function maySeeSource(WikiApiLevel $level): bool
    {
        return $level !== WikiApiLevel::Read;
    }
}
