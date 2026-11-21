<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\WikiArchivedSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

/**
 * 1.50: an archived space is read-only and open only to its managers; an active one is the inner checker's.
 */
final class WikiArchivedSpaceAccessCheckerTest extends TestCase
{
    public function testAnActiveSpaceIsTheInnerCheckersDecision(): void
    {
        $space   = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $checker = new WikiArchivedSpaceAccessChecker(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit));

        self::assertTrue($checker->isGranted(new TestUser(), WikiAction::Edit, $space));
        self::assertFalse($checker->isGranted(new TestUser(), WikiAction::Manage, $space));
    }

    public function testAnArchivedSpaceIsReadOnlyAndOnlyForManagers(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'plan', 'Plan');
        $space->changeArchived(true);
        $reader  = new WikiArchivedSpaceAccessChecker(CallbackSpaceAccessChecker::allowing(WikiAction::View, WikiAction::Edit, WikiAction::Comment));
        $manager = new WikiArchivedSpaceAccessChecker(CallbackSpaceAccessChecker::allowing(...WikiAction::cases()));
        $user    = new TestUser();

        self::assertFalse($reader->isGranted($user, WikiAction::View, $space, $page));
        self::assertFalse($reader->isGranted($user, WikiAction::Manage, $space));
        foreach ([WikiAction::View, WikiAction::History, WikiAction::Export, WikiAction::ViewComments] as $read) {
            self::assertTrue($manager->isGranted($user, $read, $space, $page), $read->value);
        }
        foreach ([WikiAction::Edit, WikiAction::Create, WikiAction::Archive, WikiAction::Restore, WikiAction::Delete, WikiAction::Purge, WikiAction::Import, WikiAction::Comment, WikiAction::AskAi] as $write) {
            self::assertFalse($manager->isGranted($user, $write, $space, $page), $write->value);
        }
        self::assertTrue($manager->isGranted($user, WikiAction::Manage, $space));
        self::assertFalse($manager->isGranted($user, WikiAction::Manage, $space, $page));

        $space->changeArchived(false);
        self::assertTrue($reader->isGranted($user, WikiAction::Comment, $space, $page));
    }
}
