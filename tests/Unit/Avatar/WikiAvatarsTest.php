<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Avatar;

use Nowo\WikiBundle\Avatar\NullWikiUserAvatarResolver;
use Nowo\WikiBundle\Avatar\WikiAvatarKey;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\RecordingAvatarResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;
use function range;

/**
 * 1.16 (avatar contract §10.2): one resolver call per batch, memoised, sanitized by the UiKit URL policy.
 */
final class WikiAvatarsTest extends TestCase
{
    public function testKeysAreTheScalarIdElseTheIdentifier(): void
    {
        self::assertSame('7', WikiAvatarKey::of(new class implements UserInterface {
            public function getId(): int
            {
                return 7;
            }

            public function getUserIdentifier(): string
            {
                return 'seven';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }
        }));
        self::assertSame('u-1', WikiAvatarKey::of(new TestUser('u-1', 'ana@example.com')));
        self::assertSame('ana@example.com', WikiAvatarKey::of(new TestUser('', 'ana@example.com')));
        self::assertSame('bob', WikiAvatarKey::of(new class implements UserInterface {
            public function getUserIdentifier(): string
            {
                return 'bob';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function eraseCredentials(): void
            {
            }
        }));
    }

    public function testTheNullResolverHasNoPictures(): void
    {
        $resolver = new NullWikiUserAvatarResolver();

        self::assertNull($resolver->urlFor(new TestUser(), 32));
        self::assertSame([], $resolver->urlsFor([new TestUser()], 32));
    }

    public function testPrimesOnceMemoisesAndSanitizes(): void
    {
        $resolver = new RecordingAvatarResolver([
            'a' => '/files/a/s64',
            'b' => 'data:image/png;base64,AAAA',
            'c' => 'https://cdn.example.com/c.png',
        ]);
        $avatars = new WikiAvatars($resolver);
        $a       = new TestUser('a', 'a@x');
        $b       = new TestUser('b', 'b@x');
        $c       = new TestUser('c', 'c@x');

        $avatars->prime([$a, $b, $a]);
        $avatars->prime([$a, $b]);
        self::assertSame([['a', 'b']], $resolver->calls);
        self::assertSame('/files/a/s64', $avatars->urlOf($a));
        self::assertNull($avatars->urlOf($b));
        // Not primed: resolved on the spot, alone.
        self::assertSame('https://cdn.example.com/c.png', $avatars->urlOf($c));
        self::assertSame([['a', 'b'], ['c']], $resolver->calls);
        self::assertSame(WikiAvatars::SIZE, $resolver->size);

        $avatars->reset();
        $avatars->urlOf($a);
        self::assertCount(3, $resolver->calls);
    }

    public function testTheMemoIsBounded(): void
    {
        $resolver = new RecordingAvatarResolver([]);
        $avatars  = new WikiAvatars($resolver);
        $users    = array_map(static fn (int $i): TestUser => new TestUser('u' . $i, 'u' . $i), range(1, WikiAvatars::MAX_MEMO));
        $avatars->prime($users);
        $avatars->prime([new TestUser('extra', 'extra')]);
        // The memo was emptied: the first user is asked again.
        $avatars->urlOf($users[0]);

        self::assertCount(3, $resolver->calls);
    }
}
