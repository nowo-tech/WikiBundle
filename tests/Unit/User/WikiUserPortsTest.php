<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\User\WikiUserId;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * 1.27: default user reference resolver and scalar user ids.
 */
final class WikiUserPortsTest extends TestCase
{
    public function testUserClassInstancesAndMissingManagerKeepTheUser(): void
    {
        $user = new TestUserEntity('id-ana');
        self::assertSame($user, (new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider(null)))->reference($user));

        $dto = new WikiSecurityUserDto('id-ana');
        self::assertSame($dto, (new DoctrineWikiUserReferenceResolver(TestUserEntity::class))->reference($dto));
        self::assertSame($dto, (new DoctrineWikiUserReferenceResolver('App\\Missing\\User', $this->provider(null)))->reference($dto), 'Unknown class');
    }

    public function testListedUsersBecomeReferencesWithTheirRawId(): void
    {
        $reference = new TestUserEntity('id-7');
        $em        = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->expects(self::once())->method('getReference')->with(TestUserEntity::class, 7)->willReturn($reference);

        $policy = new WikiUserClassPolicy('\\' . TestUserEntity::class, ['\\' . WikiSecurityUserDto::class]);
        self::assertSame($reference, (new DoctrineWikiUserReferenceResolver('\\' . TestUserEntity::class, $this->provider($em), $policy))->reference(new WikiSecurityUserDto(7)));
    }

    public function testUnlistedClassesAndUnusableIdsAreRefused(): void
    {
        $listed  = new WikiUserClassPolicy(TestUserEntity::class, [WikiSecurityUserDto::class, stdClass::class]);
        $refused = [];
        foreach ([
            'unlisted by default' => [new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider(null)), new WikiSecurityUserDto(3)],
            'no getId()'          => [new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider(null), $listed), new stdClass()],
            'empty id'            => [new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider(null), $listed), new WikiSecurityUserDto('')],
            'zero'                => [new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider(null), $listed), new WikiSecurityUserDto(0)],
            'negative string'     => [new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider(null), $listed), new WikiSecurityUserDto('-4')],
        ] as $case => [$resolver, $user]) {
            try {
                $resolver->reference($user);
                self::fail($case);
            } catch (AccessDeniedException) {
                $refused[] = $case;
            }
        }
        self::assertCount(5, $refused);
    }

    public function testANullReferenceKeepsTheUser(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->method('getReference')->willReturn(null);
        $dto = new WikiSecurityUserDto(3);
        self::assertSame($dto, (new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider($em), new WikiUserClassPolicy(TestUserEntity::class, [WikiSecurityUserDto::class])))->reference($dto));
    }

    public function testPolicy(): void
    {
        $policy = new WikiUserClassPolicy(TestUserEntity::class);
        self::assertTrue($policy->isAccepted(new TestUserEntity('a')));
        self::assertFalse($policy->isAccepted(new WikiSecurityUserDto(1)));
        self::assertSame('a', $policy->idOf(new TestUserEntity('a')));
        self::assertNull($policy->idOf(new WikiSecurityUserDto(1)));
        foreach ([1, 'x', 'acc-7'] as $id) {
            self::assertTrue(WikiUserClassPolicy::isUsableId($id));
        }
        foreach ([0, -1, '', ' ', '0', '-2', null, 1.5] as $id) {
            self::assertFalse(WikiUserClassPolicy::isUsableId($id));
        }
        self::assertTrue(WikiUserClassPolicy::isUsableId(new class implements Stringable {
            public function __toString(): string
            {
                return 'uuid';
            }
        }));
    }

    public function testScalarIds(): void
    {
        self::assertNull(WikiUserId::of(null));
        self::assertNull(WikiUserId::of(new stdClass()));
        self::assertSame('7', WikiUserId::of(new WikiSecurityUserDto(7)));
        self::assertSame('id-ana', WikiUserId::of(new TestUserEntity('id-ana')));
        self::assertNull(WikiUserId::of(new WikiSecurityUserDto('')));
        self::assertSame('0195-uuid', WikiUserId::of(new class {
            public function getId(): Stringable
            {
                return new class implements Stringable {
                    public function __toString(): string
                    {
                        return '0195-uuid';
                    }
                };
            }
        }));
        self::assertNull(WikiUserId::of(new class {
            /** @return list<int> */
            public function getId(): array
            {
                return [1];
            }
        }));
    }

    private function provider(?EntityManagerInterface $em): WikiEntityManagerProvider
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em ?? $this->createStub(EntityManagerInterface::class));

        return new WikiEntityManagerProvider($registry);
    }
}
