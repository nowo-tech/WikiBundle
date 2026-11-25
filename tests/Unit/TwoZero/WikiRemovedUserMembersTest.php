<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\TwoZero;

use Error;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEditorOptionsEvent;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * 2.0 (audit ZAQ-1, ZAQ-8): the removed public `$user` members are private, so a host that still reads `->user` gets an
 * Error (never a silent null), and building a principal from a Symfony user (its constructor) is deprecated for 3.0.
 */
final class WikiRemovedUserMembersTest extends TestCase
{
    public function testReadingTheRemovedUserMembersThrows(): void
    {
        $user  = new TestUser();
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        foreach ([
            WikiApiPrincipal::of(WikiUserRef::ofId(TestUser::class, 'u-1'), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n'),
            new WikiQuotaCheckEvent(WikiQuotaCheckEvent::PAGE_CREATE, $space, $user),
            new WikiEditorOptionsEvent($space, null, $user),
        ] as $holder) {
            try {
                // @phpstan-ignore property.private (the removed member, read on purpose)
                $holder->user;
                self::fail($holder::class . '::$user must not be readable.');
            } catch (Error $error) {
                self::assertStringContainsString('Cannot access private property ' . $holder::class . '::$user', $error->getMessage());
            }
        }
    }

    public function testTheSymfonyUserConstructorIsDeprecatedAndOfIsNot(): void
    {
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $legacy = new WikiApiPrincipal(new TestUser('u-1', 'bob'), WikiApiLevel::Read, WikiApiWhere::everything(), 't', 'n');
            $ref    = WikiApiPrincipal::of(WikiUserRef::ofId(TestUser::class, 'u-2'), WikiApiLevel::Edit, WikiApiWhere::everything(), 'tok', 'name', 'mcp');
        } finally {
            restore_error_handler();
        }

        self::assertCount(1, $notices);
        self::assertStringContainsString('Building ' . WikiApiPrincipal::class . ' with a Symfony user (its constructor) is deprecated, 3.0 removes it', $notices[0]);
        self::assertSame('bob', $legacy->userRef()->identifier);
        self::assertSame('u-2', $ref->userRef()->id);
        self::assertSame([WikiApiLevel::Edit, 'tok', 'name', 'mcp'], [$ref->level, $ref->tokenId, $ref->tokenName, $ref->channel]);
    }
}
