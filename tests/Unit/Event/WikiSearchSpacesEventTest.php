<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Event;

use DateTimeImmutable;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiSearchSpace;
use Nowo\WikiBundle\Event\WikiSearchSpacesEvent;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use PHPUnit\Framework\TestCase;

use function ucfirst;

/**
 * 1.46: the spaces event of the SearchKit group narrows only, an exclusion wins, and it cannot be stopped.
 */
final class WikiSearchSpacesEventTest extends TestCase
{
    public function testExclusionsNarrowOnlyAndCannotBeUndoneOrStopped(): void
    {
        $eng   = new WikiSearchSpace($this->snapshot('11111111-1111-4111-8111-111111111111', 'eng'), 'tenant_path:0');
        $ops   = new WikiSearchSpace($this->snapshot('22222222-2222-4222-8222-222222222222', 'ops'), null);
        $event = new WikiSearchSpacesEvent('u-ana', 'owner-1', 'es', [$eng, $ops]);

        self::assertSame('u-ana', $event->userIdentifier);
        self::assertSame('owner-1', $event->scope);
        self::assertSame('es', $event->locale);
        self::assertSame([$eng, $ops], $event->spaces());
        self::assertFalse($event->isExcluded($eng->space->id));

        $event->exclude($ops->space->id);
        $event->exclude('33333333-3333-4333-8333-333333333333');
        $event->stopPropagation();

        self::assertTrue($event->isExcluded($ops->space->id));
        self::assertFalse($event->isExcluded($eng->space->id));
        self::assertFalse($event->isExcluded('33333333-3333-4333-8333-333333333333'));
        self::assertFalse($event->isPropagationStopped());
        self::assertSame([$eng, $ops], $event->spaces());

        $event->excludeAll();
        self::assertTrue($event->isExcluded($eng->space->id));
        self::assertTrue($event->isExcluded($ops->space->id));
    }

    private function snapshot(string $id, string $slug): WikiSpaceSnapshot
    {
        $at = new DateTimeImmutable('2026-11-19 10:00:00');

        return new WikiSpaceSnapshot($id, $slug, ucfirst($slug), WikiSpaceOwnerScope::Team, 'owner-1', $at, $at);
    }
}
