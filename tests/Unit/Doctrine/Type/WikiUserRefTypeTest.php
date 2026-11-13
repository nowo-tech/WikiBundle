<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Doctrine\Type;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use InvalidArgumentException;
use Nowo\WikiBundle\Doctrine\Type\WikiUserRefType;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 1.32.0 (`database.user_reference: id`): the column type of the stored user ids.
 */
final class WikiUserRefTypeTest extends TestCase
{
    public function testWritesTheExactIdOfEveryAcceptedValue(): void
    {
        $type     = new WikiUserRefType();
        $platform = new SQLitePlatform();

        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertSame('Bób', $type->convertToDatabaseValue(new WikiStoredUserRef('Bób'), $platform));
        self::assertSame('7', $type->convertToDatabaseValue('7', $platform));
        self::assertSame('u-1', $type->convertToDatabaseValue(new TestUser('u-1'), $platform));
    }

    /** @return iterable<string, array{mixed}> */
    public static function valuesWithoutId(): iterable
    {
        yield 'object without getId()' => [new stdClass()];
        yield 'integer' => [7];
    }

    #[DataProvider('valuesWithoutId')]
    public function testRefusesAValueWithoutId(mixed $value): void
    {
        $this->expectException(InvalidType::class);

        (new WikiUserRefType())->convertToDatabaseValue($value, new SQLitePlatform());
    }

    /** @return iterable<string, array{mixed}> */
    public static function lookalikes(): iterable
    {
        yield 'trailing space' => ['bob '];
        yield 'newline' => ["bob\n"];
        yield 'nbsp' => ["bob\u{A0}"];
        yield 'zwsp' => ["bo\u{200B}b"];
        yield 'too long' => [str_repeat('a', 129)];
        yield 'user with a padded id' => [new TestUser(' bob')];
    }

    #[DataProvider('lookalikes')]
    public function testRefusesAnIdThatCannotBeMatchedExactly(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WikiUserRefType())->convertToDatabaseValue($value, new SQLitePlatform());
    }

    public function testReadsAStoredReference(): void
    {
        $type     = new WikiUserRefType();
        $platform = new SQLitePlatform();

        self::assertEquals(new WikiStoredUserRef('bob'), $type->convertToPHPValue('bob', $platform));
        self::assertEquals(new WikiStoredUserRef('42'), $type->convertToPHPValue(42, $platform));
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertNull($type->convertToPHPValue('', $platform));
        self::assertNull($type->convertToPHPValue('bob ', $platform), 'a legacy value that cannot be matched exactly identifies nobody');
        self::assertNull($type->convertToPHPValue(1.5, $platform));
    }
}
