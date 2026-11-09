<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\User;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserId;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

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

    public function testOtherUsersBecomeReferencesWithTheirRawId(): void
    {
        $reference = new TestUserEntity('id-7');
        $em        = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->expects(self::once())->method('getReference')->with(TestUserEntity::class, 7)->willReturn($reference);

        self::assertSame($reference, (new DoctrineWikiUserReferenceResolver('\\' . TestUserEntity::class, $this->provider($em)))->reference(new WikiSecurityUserDto(7)));
    }

    public function testUsersWithoutIdOrANullReferenceKeepTheUser(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $em->method('getReference')->willReturn(null);
        $resolver = new DoctrineWikiUserReferenceResolver(TestUserEntity::class, $this->provider($em));

        $object = new stdClass();
        self::assertSame($object, $resolver->reference($object));
        $empty = new WikiSecurityUserDto('');
        self::assertSame($empty, $resolver->reference($empty));
        $dto = new WikiSecurityUserDto(3);
        self::assertSame($dto, $resolver->reference($dto));
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
