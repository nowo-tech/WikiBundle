<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use DateTimeImmutable;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiPlanGate;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiRateLimiter;
use Nowo\WikiBundle\Api\Hook\NullWikiApiUsers;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiCapability;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiApiIdempotencyRecord;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiApiWriteEvent;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

use function count;

/**
 * 1.24 (spec 218): the level table, *where*, the denying defaults and the small values.
 */
final class WikiApiValuesTest extends TestCase
{
    public function testTheLevelTableIsClosedOverEveryAction(): void
    {
        $expected = [
            'view'              => [true, true, true],
            'history'           => [true, true, true],
            'view_comments'     => [true, true, true],
            'create'            => [false, true, true],
            'edit'              => [false, true, true],
            'comment'           => [false, true, true],
            'resolve_comment'   => [false, true, true],
            'archive'           => [false, true, true],
            'restore'           => [false, false, true],
            'moderate_comments' => [false, false, true],
            'manage'            => [false, false, true],
            'import'            => [false, false, false],
            'export'            => [false, false, false],
            'ask_ai'            => [false, false, false],
            'delete'            => [false, false, false],
            'purge'             => [false, false, false],
        ];
        // A new WikiAction must be decided here: this list names every case.
        self::assertCount(count(WikiAction::cases()), $expected);
        foreach (WikiAction::cases() as $action) {
            self::assertArrayHasKey($action->value, $expected);
            foreach ([WikiApiLevel::Read, WikiApiLevel::Edit, WikiApiLevel::Full] as $i => $level) {
                self::assertSame($expected[$action->value][$i], WikiApiCapability::allows($level, $action), $action->value . ' at ' . $level->value);
            }
        }

        self::assertFalse(WikiApiCapability::maySeeSource(WikiApiLevel::Read));
        self::assertTrue(WikiApiCapability::maySeeSource(WikiApiLevel::Edit));
        self::assertFalse(WikiApiCapability::actsOnAnyone(WikiApiLevel::Edit));
        self::assertTrue(WikiApiCapability::actsOnAnyone(WikiApiLevel::Full));
    }

    public function testWhereOnlyNarrowsAndRestrictedWikisMustBeNamed(): void
    {
        $eng        = $this->wiki('eng', 'owner-1');
        $orphan     = $this->wiki('general', null);
        $restricted = $this->wiki('hr', 'owner-1', true);

        $everything = WikiApiWhere::everything(['owner-9']);
        self::assertTrue($everything->reaches($eng));
        self::assertTrue($everything->reaches($orphan));
        self::assertFalse($everything->reaches($restricted));
        self::assertFalse($everything->reaches($this->wiki('x', 'owner-9')));

        $owner = WikiApiWhere::limitedTo(['owner-1'], []);
        self::assertTrue($owner->reaches($eng));
        self::assertFalse($owner->reaches($orphan));
        self::assertFalse($owner->reaches($restricted));

        $named = WikiApiWhere::limitedTo([], ['hr', 'general']);
        self::assertTrue($named->reaches($restricted));
        self::assertTrue($named->reaches($orphan));
        self::assertFalse($named->reaches($eng));
        self::assertFalse(WikiApiWhere::limitedTo([], [])->reaches($eng));

        // A new wiki's owner (audit IF-L7).
        self::assertTrue($everything->reachesOwner('owner-1'));
        self::assertFalse($everything->reachesOwner('owner-9'));
        self::assertTrue($owner->reachesOwner('owner-1'));
        self::assertFalse($owner->reachesOwner('owner-2'));
        self::assertFalse($named->reachesOwner('owner-1'));
    }

    public function testTheDenyingDefaults(): void
    {
        $principal = new WikiApiPrincipal(new TestUser(), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n');
        $limiter   = new DenyingWikiApiRateLimiter();
        self::assertFalse($limiter->consume($principal)->accepted);
        self::assertFalse($limiter->failedTokenState('127.0.0.1')->accepted);
        $limiter->registerFailedToken('127.0.0.1');
        self::assertFalse((new DenyingWikiApiPlanGate())->allowsWiki(new TestUser(), $this->wiki('eng', null)));
        self::assertNull((new NullWikiApiUsers())->apiId(new TestUser()));
        self::assertSame('api', $principal->channel);
    }

    public function testSmallValues(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $wiki  = $this->wiki('eng', 'owner-1')->withSpace($space);
        self::assertSame($space, $wiki->space);
        self::assertSame('owner-1', $wiki->ownerKey);

        $refusal = WikiApiRefusal::archivedWiki();
        self::assertSame([403, 'WIKI_002', 'This wiki is archived'], [$refusal->status, $refusal->ecode, $refusal->getMessage()]);
        self::assertSame('Invalid field: title', WikiApiRefusal::invalid('title')->getMessage());

        $record = new WikiApiIdempotencyRecord('s', 'k', 'f', new DateTimeImmutable(), new DateTimeImmutable());
        self::assertNull($record->status);

        $event = new WikiApiWriteEvent(new WikiApiPrincipal(new TestUser(), WikiApiLevel::Edit, WikiApiWhere::everything(), 't', 'n'), 'page.update', 'eng', 's', 'p', ['revision' => 'r'], ['title']);
        self::assertSame(['title'], $event->changedFields);
    }

    private function wiki(string $slug, ?string $owner, bool $restricted = false): WikiApiWiki
    {
        return new WikiApiWiki($slug, null, $slug, ownerKey: $owner, restricted: $restricted);
    }
}
