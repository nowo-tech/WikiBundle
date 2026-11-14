<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\ValueObject;

use InvalidArgumentException;
use Nowo\WikiBundle\ValueObject\WikiPagePlacement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * 1.35: the place the new-page form asks for, as an intent (PT-L3): `child:<id>`, `above:<id>` or `below:<id>`.
 */
final class WikiPagePlacementTest extends TestCase
{
    public function testNothingAskedIsNoPlace(): void
    {
        self::assertNull(WikiPagePlacement::fromValue(null));
        self::assertNull(WikiPagePlacement::fromValue(''));
        self::assertNull(WikiPagePlacement::fromValue('  '));
    }

    public function testReadsTheIntent(): void
    {
        $id    = '0b6f5a8e-1c2d-4e5f-8a9b-0c1d2e3f4a5b';
        $child = WikiPagePlacement::fromValue(' child:' . $id . ' ');
        self::assertNotNull($child);
        self::assertSame([WikiPagePlacement::CHILD, $id], [$child->kind, $child->pageId]);
        self::assertTrue($child->isChild());
        self::assertSame('child:' . $id, $child->value());

        $above = WikiPagePlacement::fromValue('above:' . $id);
        self::assertNotNull($above);
        self::assertFalse($above->isChild());
        self::assertFalse($above->isAfter());
        $below = WikiPagePlacement::fromValue('below:' . $id);
        self::assertNotNull($below);
        self::assertTrue($below->isAfter());
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalid(): iterable
    {
        yield 'no kind' => ['abc'];
        yield 'no id' => ['child:'];
        yield 'unknown kind' => ['side:abc'];
        yield 'a number (the 1.35 drafts)' => ['2'];
        yield 'quote in the id' => ["child:x'1"];
        yield 'id too long' => ['child:' . str_repeat('a', 37)];
        yield 'array' => [['child:a']];
        yield 'int' => [2];
    }

    #[DataProvider('invalid')]
    public function testRefusesValuesThatAreNoPlace(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        WikiPagePlacement::fromValue($value);
    }

    public function testTheConstructorChecksToo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WikiPagePlacement('inside', 'abc');
    }
}
