<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\User;

use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\User\WikiUserDirectoryInterface;
use Nowo\WikiBundle\User\WikiUserFields;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use function str_repeat;

/**
 * 1.32.0 (`database.user_reference: id`): the host users behind stored ids, the exact id rules and the resolver.
 */
final class WikiUsersTest extends TestCase
{
    public function testTheDirectoryIsAskedOncePerUnitOfWorkAndOnlyExactAnswersCount(): void
    {
        $directory = new class implements WikiUserDirectoryInterface {
            /** @var list<string> */
            public array $asked = [];

            public function find(string $id): ?object
            {
                $this->asked[] = $id;

                return match ($id) {
                    'bob'   => new TestUser('bob', 'bob@x'),
                    'eve'   => new TestUser('Eve', 'eve@x'), // a look-alike answer: ignored
                    default => null,
                };
            }
        };
        $users = new WikiUsers('id', $directory);

        self::assertTrue($users->idMode());
        $bob = $users->find('bob');
        self::assertInstanceOf(TestUser::class, $bob);
        self::assertSame($bob, $users->find('bob'));
        self::assertNull($users->find('eve'));
        self::assertNull($users->find('nobody'));
        self::assertNull($users->find('bob '), 'never asked for an id that cannot be matched exactly');
        self::assertSame(['bob', 'eve', 'nobody'], $directory->asked);

        self::assertSame($bob, $users->resolve(new WikiStoredUserRef('bob')));
        $unknown = new WikiStoredUserRef('nobody');
        self::assertSame($unknown, $users->resolve($unknown));
        $entity = new TestUserEntity('x');
        self::assertSame($entity, $users->resolve($entity));
        self::assertNull($users->resolve(null));

        $users->reset();
        $users->find('bob');
        self::assertSame(['bob', 'eve', 'nobody', 'bob'], $directory->asked, 'asked again after a boundary');
    }

    /**
     * REQ-RUNTIME-002 (kernel kept alive, no services resetter): a user the host deleted or renamed between two
     * requests is never served from the previous request's memo; every boundary empties it.
     */
    public function testEveryBoundaryEmptiesTheMemo(): void
    {
        $directory = new class implements WikiUserDirectoryInterface {
            public ?object $bob = null;

            public function find(string $id): ?object
            {
                return $id === 'bob' ? $this->bob : null;
            }
        };
        $users    = new WikiUsers('id', $directory);
        $boundary = new WikiRequestBoundaryResetter([$users]);
        $kernel   = $this->createStub(HttpKernelInterface::class);

        $directory->bob = new TestUser('bob', 'first@x');
        self::assertNotNull($users->find('bob'));
        foreach ([
            'request'                         => static fn () => $boundary->onKernelRequest(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST)),
            'terminate / console / messenger' => static fn () => $boundary->reset(),
            'kernel.reset'                    => static fn () => $users->reset(),
        ] as $name => $cross) {
            $directory->bob = new TestUser('bob', $name . '@x'); // renamed by the host after the previous unit of work
            $cross();
            $found = $users->find('bob');
            self::assertInstanceOf(TestUser::class, $found);
            self::assertSame($name . '@x', $found->getUserIdentifier(), $name);
        }

        // A sub-request keeps the memo of its main request.
        $directory->bob = new TestUser('bob', 'first@x');
        $users->find('bob');
        $directory->bob = null;
        $boundary->onKernelRequest(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::SUB_REQUEST));
        self::assertNotNull($users->find('bob'));
    }

    public function testTheMemoIsBounded(): void
    {
        $directory = new class implements WikiUserDirectoryInterface {
            public int $asked = 0;

            public function find(string $id): ?object
            {
                ++$this->asked;

                return null;
            }
        };
        $users = new WikiUsers('id', $directory);
        for ($i = 0; $i <= WikiUsers::MAX_FOUND; ++$i) {
            $users->find('u' . $i);
        }
        $users->find('u1');

        self::assertSame(WikiUsers::MAX_FOUND + 2, $directory->asked, 'the memo was emptied when full');
    }

    public function testWithoutDirectoryOrInEntityModeNothingIsFound(): void
    {
        $users = new WikiUsers();
        self::assertFalse($users->idMode());
        self::assertNull($users->find('bob'));
        $ref = new WikiStoredUserRef('bob');
        self::assertSame($ref, $users->resolve($ref));
    }

    public function testExactIds(): void
    {
        self::assertSame('bob', WikiUserFields::exactId(new WikiStoredUserRef('bob')));
        self::assertSame('bob', WikiUserFields::exactId(new TestUser('bob')));
        self::assertNull(WikiUserFields::exactId(new TestUser('bob ')));
        self::assertNull(WikiUserFields::exactId(new TestUser(str_repeat('a', 129))));
        self::assertNull(WikiUserFields::exactId(new stdClass()));
        self::assertTrue(WikiUserFields::is(new WikiStoredUserRef('bob'), 'bob'));
        self::assertFalse(WikiUserFields::is(new WikiStoredUserRef('Bob'), 'bob'));
        self::assertFalse(WikiUserFields::is(null, 'bob'));
    }

    public function testTheResolverStoresTheExactIdInTheIdMode(): void
    {
        $policy   = new WikiUserClassPolicy(TestUserEntity::class, [WikiSecurityUserDto::class]);
        $resolver = new DoctrineWikiUserReferenceResolver(TestUserEntity::class, null, $policy, 'id');

        self::assertEquals(new WikiStoredUserRef('ana'), $resolver->reference(new TestUserEntity('ana')));
        self::assertEquals(new WikiStoredUserRef('7'), $resolver->reference(new WikiSecurityUserDto(7)));
        $stored = new WikiStoredUserRef('ana');
        self::assertSame($stored, $resolver->reference($stored));

        $refused = 0;
        foreach ([new TestUser('ana'), new TestUserEntity('ana '), new TestUserEntity("an\u{200B}a")] as $user) {
            try {
                $resolver->reference($user);
            } catch (AccessDeniedException) {
                ++$refused;
            }
        }
        self::assertSame(3, $refused, 'another class, or an id that cannot be matched exactly');

        // Without a policy only user_class instances are accepted.
        self::assertEquals(new WikiStoredUserRef('ana'), (new DoctrineWikiUserReferenceResolver(TestUserEntity::class, null, null, 'id'))->reference(new TestUserEntity('ana')));
    }
}
