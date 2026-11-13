<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\ValueObject;

use InvalidArgumentException;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class WikiUserRefTest extends TestCase
{
    public function testItKeepsTheCopiedValuesAndTheOriginalObject(): void
    {
        $user = new stdClass();
        $ref  = new WikiUserRef('App\\User', '42', 'alice', ['ROLE_A', 7, 'ROLE_B'], $user);

        self::assertSame('App\\User', $ref->userClass);
        self::assertSame('42', $ref->id);
        self::assertSame('alice', $ref->identifier);
        self::assertSame(['ROLE_A', 'ROLE_B'], $ref->roles);
        self::assertSame($user, $ref->user());
        self::assertTrue($ref->hasId());
        self::assertTrue($ref->hasRole('ROLE_A'));
        self::assertFalse($ref->hasRole('role_a'));
    }

    public function testOfIdHasNoObject(): void
    {
        $ref = WikiUserRef::ofId('App\\User', 'u-1', 'bob', ['ROLE_USER']);

        self::assertNull($ref->user());
        self::assertSame(['ROLE_USER'], $ref->roles);
        self::assertSame('bob', $ref->identifier);
    }

    public function testAUserWithoutIdIsNobody(): void
    {
        $ref = new WikiUserRef('App\\User', null);

        self::assertFalse($ref->hasId());
        self::assertFalse($ref->isSameUserAs($ref));
    }

    public function testSameUserNeedsTheSameClassAndExactlyTheSameId(): void
    {
        $ref = WikiUserRef::ofId('App\\User', 'bob');

        self::assertTrue($ref->isSameUserAs(WikiUserRef::ofId('App\\User', 'bob', 'other')));
        self::assertFalse($ref->isSameUserAs(WikiUserRef::ofId('App\\User', 'Bob')));
        self::assertFalse($ref->isSameUserAs(WikiUserRef::ofId('App\\Admin', 'bob')));
        self::assertFalse($ref->isSameUserAs(WikiUserRef::ofId('App\\User', "bo\u{0301}b")));
    }

    #[DataProvider('unusableIds')]
    public function testAnUnusableIdIsRefused(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WikiUserRef('App\\User', $id);
    }

    #[DataProvider('unusableIds')]
    public function testAStoredReferenceRefusesAnUnusableId(string $id): void
    {
        self::assertFalse(WikiStoredUserRef::isValid($id));
        $this->expectException(InvalidArgumentException::class);

        new WikiStoredUserRef($id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableIds(): iterable
    {
        yield 'empty' => [''];
        yield 'trailing space' => ['bob '];
        yield 'leading space' => [' bob'];
        yield 'newline' => ["bob\n"];
        yield 'nbsp' => ["bob\u{00A0}"];
        yield 'zwsp' => ["bo\u{200B}b"];
        yield 'bom' => ["\u{FEFF}bob"];
        yield 'bidi' => ["bob\u{202E}"];
        yield 'invalid utf-8' => ["bo\xC3"];
        yield 'too long' => [str_repeat('a', 129)];
    }

    public function testAStoredReferenceKeepsItsIdExactly(): void
    {
        $ref = new WikiStoredUserRef('Bób-1');

        self::assertSame('Bób-1', $ref->getId());
        self::assertSame('Bób-1', (string) $ref);
        self::assertTrue(WikiStoredUserRef::isValid(str_repeat('é', 128)));
    }
}
