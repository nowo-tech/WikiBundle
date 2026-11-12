<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\ValueObject;

use Nowo\WikiBundle\ValueObject\WikiOwnerScopeId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * 1.31.1 (house rule LQ): the owner id of a space is refused when a collation could confuse it with another one.
 */
final class WikiOwnerScopeIdTest extends TestCase
{
    #[DataProvider('usable')]
    public function testAcceptsIdsThatCannotBeConfused(string $id): void
    {
        self::assertTrue(WikiOwnerScopeId::isUsable($id));
    }

    /** @return iterable<string, array{string}> */
    public static function usable(): iterable
    {
        yield 'integer id' => ['42'];
        yield 'uuid' => ['0190a8f2-6c7e-7b3a-9c1d-2f4e5a6b7c8d'];
        yield 'e-mail' => ['Alice@example.test'];
        yield 'inner space' => ['Alice Smith'];
        yield 'accented' => ["\u{e1}lice"];
        yield '64 characters' => [str_repeat("\u{e9}", 64)];
    }

    #[DataProvider('unusable')]
    public function testRefusesIdsThatACollationCouldConfuse(string $id): void
    {
        self::assertFalse(WikiOwnerScopeId::isUsable($id));
    }

    /** @return iterable<string, array{string}> */
    public static function unusable(): iterable
    {
        yield 'empty' => [''];
        yield 'trailing space' => ['alice '];
        yield 'leading space' => [' alice'];
        yield 'trailing no-break space' => ["alice\u{a0}"];
        yield 'trailing tab' => ["alice\t"];
        yield 'inner newline' => ["ali\nce"];
        yield 'inner NUL' => ["ali\0ce"];
        yield 'zero-width space' => ["ali\u{200B}ce"];
        yield 'bidi mark' => ["alice\u{200F}x"];
        yield 'byte order mark' => ["\u{FEFF}alice"];
        yield 'invalid UTF-8' => ["alic\xC3"];
        yield '65 characters' => [str_repeat('a', 65)];
    }
}
