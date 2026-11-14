<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\ValueObject;

use InvalidArgumentException;
use Nowo\WikiBundle\ValueObject\WikiPagePlacement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * 1.35: the place of a new page read from the hidden fields of the new-page form.
 */
final class WikiPagePlacementTest extends TestCase
{
    public function testNothingAskedIsThePlaceBefore135(): void
    {
        $placement = WikiPagePlacement::fromValues(null, null);

        self::assertNull($placement->parentId);
        self::assertNull($placement->position);
        self::assertFalse($placement->isPositioned());
        self::assertSame(0, $placement->index());
        self::assertFalse(WikiPagePlacement::fromValues('', '')->isPositioned());
        self::assertNull(WikiPagePlacement::fromValues('  ', '')->parentId);
    }

    public function testReadsTheParentAndTheOneBasedPosition(): void
    {
        $placement = WikiPagePlacement::fromValues(' 0b6f5a8e-1c2d-4e5f-8a9b-0c1d2e3f4a5b ', '3');

        self::assertSame('0b6f5a8e-1c2d-4e5f-8a9b-0c1d2e3f4a5b', $placement->parentId);
        self::assertSame(3, $placement->position);
        self::assertTrue($placement->isPositioned());
        self::assertSame(2, $placement->index());
        self::assertSame(0, (new WikiPagePlacement(null, 1))->index());
        self::assertSame(WikiPagePlacement::MAX_POSITION, WikiPagePlacement::fromValues(null, '100000')->position);
    }

    /**
     * @return iterable<string, array{0: mixed, 1: mixed}>
     */
    public static function invalid(): iterable
    {
        yield 'position zero' => [null, '0'];
        yield 'negative position' => [null, '-1'];
        yield 'not a number' => [null, 'abc'];
        yield 'decimal' => [null, '1.5'];
        yield 'too large' => [null, '100001'];
        yield 'too long' => [null, '0000001'];
        yield 'array position' => [null, ['1']];
        yield 'int position (not a form value)' => [null, 2];
        yield 'array parent' => [['x'], null];
        yield 'parent with a quote' => ["x'1", null];
        yield 'parent too long' => [str_repeat('a', 37), null];
    }

    #[DataProvider('invalid')]
    public function testRefusesValuesThatAreNoPlace(mixed $parent, mixed $position): void
    {
        $this->expectException(InvalidArgumentException::class);
        WikiPagePlacement::fromValues($parent, $position);
    }

    public function testTheConstructorChecksToo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WikiPagePlacement('');
    }
}
