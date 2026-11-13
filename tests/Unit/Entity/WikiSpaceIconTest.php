<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Entity;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function strlen;

/**
 * 1.32: the optional UX icon of a space.
 */
final class WikiSpaceIconTest extends TestCase
{
    public function testDefaultsToNullAndAcceptsValidNames(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
        self::assertNull($space->getIcon());

        $space->setIcon('tabler:rocket');
        self::assertSame('tabler:rocket', $space->getIcon());

        $space->setIcon(null);
        self::assertNull($space->getIcon());
    }

    public function testAcceptsAConfiguredPrefix(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
        $space->setIcon('lucide:book-open', ['tabler', 'lucide']);

        self::assertSame('lucide:book-open', $space->getIcon());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalid(): iterable
    {
        yield 'no prefix' => ['book'];
        yield 'empty' => [''];
        yield 'uppercase' => ['tabler:Book'];
        yield 'underscore' => ['tabler:book_open'];
        yield 'two colons' => ['tabler:a:b'];
        yield 'wrong prefix' => ['mdi:book'];
        yield 'trailing newline' => ["tabler:book\n"];
        yield 'leading space' => [' tabler:book'];
        yield 'too long' => ['tabler:' . str_repeat('a', 58)];
    }

    #[DataProvider('invalid')]
    public function testRejectsInvalidNames(string $icon): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
        $space->setIcon('tabler:book');

        try {
            $space->setIcon($icon);
            self::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            self::assertSame('tabler:book', $space->getIcon());
        }
    }

    public function testMaxLengthBoundary(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1');
        $name  = 'tabler:' . str_repeat('a', 57);
        $space->setIcon($name);

        self::assertSame(64, strlen($name));
        self::assertSame($name, $space->getIcon());
    }

    public function testWrongPrefixForTheConfiguredList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, '1'))->setIcon('tabler:book', ['lucide']);
    }
}
