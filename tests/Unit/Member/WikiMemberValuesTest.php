<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Member;

use InvalidArgumentException;
use Nowo\WikiBundle\Member\NullWikiMemberBadgeProvider;
use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberRef;
use Nowo\WikiBundle\Tests\Stub\ProxiedMember;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

use function class_exists;
use function str_repeat;

/**
 * Spec 234: the member badge port's value objects (framework-free) and its default provider.
 */
final class WikiMemberValuesTest extends TestCase
{
    public function testABadgeIsPlainTextWithAnAllowListedVariant(): void
    {
        $badge = new WikiMemberBadge('  Agent ', WikiMemberBadge::VARIANT_AGENT, " Service\naccount ");
        self::assertSame('Agent', $badge->label);
        self::assertSame('agent', $badge->variant);
        self::assertSame('Service account', $badge->title);

        $plain = new WikiMemberBadge('<script>alert(1)</script>');
        self::assertSame('<script>alert(1)</script>', $plain->label, 'kept as text; the templates escape it');
        self::assertSame('neutral', $plain->variant);
        self::assertNull($plain->title);

        self::assertSame(str_repeat('a', WikiMemberBadge::MAX_LABEL), (new WikiMemberBadge(str_repeat('a', 60), 'info'))->label);
        self::assertSame(str_repeat('t', WikiMemberBadge::MAX_TITLE), (new WikiMemberBadge('x', 'info', str_repeat('t', 200)))->title);
    }

    public function testAnEmptyLabelIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a label');

        new WikiMemberBadge(" \u{0007} ");
    }

    public function testAVariantOutsideTheAllowListIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown member badge variant "btn-danger"');

        new WikiMemberBadge('Agent', 'btn-danger');
    }

    public function testAMemberIsItsClassAndId(): void
    {
        $ref = WikiMemberRef::of(new TestUserEntity('ana'));
        self::assertInstanceOf(WikiMemberRef::class, $ref);
        self::assertSame(TestUserEntity::class, $ref->userClass);
        self::assertSame('ana', $ref->id);
        self::assertTrue($ref->isSameMemberAs(new WikiMemberRef(TestUserEntity::class, 'ana')));
        self::assertFalse($ref->isSameMemberAs(new WikiMemberRef(TestUserEntity::class, 'Ana')), 'exact, case-sensitive');
        self::assertFalse($ref->isSameMemberAs(new WikiMemberRef(stdClass::class, 'ana')), 'same id, other class');

        $int = WikiMemberRef::of(new class {
            public function getId(): int
            {
                return 7;
            }
        });
        self::assertSame('7', $int?->id);

        $stringable = WikiMemberRef::of(new class {
            public function getId(): Stringable
            {
                return new class implements Stringable {
                    public function __toString(): string
                    {
                        return '0b6f4f0e';
                    }
                };
            }
        });
        self::assertSame('0b6f4f0e', $stringable?->id);
    }

    public function testAProxyClassIsTheEntityClass(): void
    {
        $proxyClass = 'Proxies\\__CG__\\Nowo\\WikiBundle\\Tests\\Stub\\ProxiedMember';
        if (!class_exists($proxyClass, false)) {
            eval('namespace Proxies\\__CG__\\Nowo\\WikiBundle\\Tests\\Stub; final class ProxiedMember extends \\Nowo\\WikiBundle\\Tests\\Stub\\ProxiedMember {}');
        }
        $proxy = new $proxyClass();
        self::assertInstanceOf(ProxiedMember::class, $proxy);
        self::assertSame(ProxiedMember::class, WikiMemberRef::of($proxy)?->userClass);
    }

    public function testAUserWithoutAnExactIdHasNoReference(): void
    {
        self::assertNull(WikiMemberRef::of(new stdClass()));
        self::assertNull(WikiMemberRef::of(new TestUserEntity(' ana')));
        self::assertNull(WikiMemberRef::of(new TestUserEntity(str_repeat('a', 129))));
        self::assertNull(WikiMemberRef::of(new TestUserEntity("a\u{200B}b")));
        self::assertNotNull(WikiMemberRef::of(new TestUserEntity(str_repeat('é', 128))));
    }

    public function testAnInvalidIdIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WikiMemberRef(TestUserEntity::class, '');
    }

    public function testInvalidUtf8IsRefused(): void
    {
        self::assertFalse(WikiMemberRef::isValidId("\xC3\x28"));
    }

    public function testTheDefaultProviderGivesNoBadge(): void
    {
        $provider = new NullWikiMemberBadgeProvider();
        self::assertSame([], $provider->badgesFor(new WikiMemberRef(TestUserEntity::class, 'ana')));
        self::assertSame([], $provider->badgesForMany([new WikiMemberRef(TestUserEntity::class, 'ana')]));
    }
}
