<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Api\Repository\WikiApiUsersReader;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\User\WikiUserDirectoryInterface;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\TestCase;

/**
 * 1.33.0 (`database.user_reference: id`): the REST API finds the authors of the page list through the directory, never
 * through the user table.
 */
final class WikiApiUsersReaderIdModeTest extends TestCase
{
    public function testAuthorsComeFromTheDirectoryByExactId(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManager');
        $directory = new class implements WikiUserDirectoryInterface {
            public function find(string $id): ?object
            {
                return match ($id) {
                    '7'     => new TestUser('7'),
                    'bob'   => new TestUser('Bob'),
                    default => null,
                };
            }
        };
        $reader = new WikiApiUsersReader(new WikiEntityManagerProvider($registry), TestUser::class, new WikiUsers('id', $directory));

        $users = $reader->byIds(['7', 'bob', 'gone', '7', 'bob ']);

        self::assertSame(['7', 'bob', 'gone'], array_map('strval', array_keys($users)));
        self::assertInstanceOf(TestUser::class, $users['7']);
        self::assertEquals(new WikiStoredUserRef('bob'), $users['bob'], 'a look-alike answer is ignored');
        self::assertEquals(new WikiStoredUserRef('gone'), $users['gone']);
        self::assertSame([], $reader->byIds([]));
    }
}
