<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\TwoZero;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestHostBookmark;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

/**
 * 2.0 (REQ-ARCH-001 rule 6, lesson W1-2): the private manager and the rows of the application's manager (the source).
 */
final class WikiEntityManagerHostRowsTest extends TestCase
{
    private EntityManager $host;

    private WikiEntityManager $private;

    protected function setUp(): void
    {
        $this->host = WikiTestDatabase::create();
        $registry   = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->host);
        $this->private = new WikiEntityManager($registry);
    }

    protected function tearDown(): void
    {
        $this->host->getConnection()->close();
    }

    public function testLockRefusesARowOfTheApplicationsManagerAndLocksItsOwn(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->host->persist($space);
        $this->host->flush();

        $own = $this->private->find(WikiSpace::class, $space->getId());
        self::assertInstanceOf(WikiSpace::class, $own);
        $this->private->getConnection()->beginTransaction();
        $this->private->lock($own, LockMode::PESSIMISTIC_WRITE);
        $this->private->getConnection()->rollBack();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot lock this ' . WikiSpace::class);
        $this->private->lock($space, LockMode::PESSIMISTIC_WRITE);
    }

    public function testAWikiRowOfAnotherManagerOnANewRowBecomesAReference(): void
    {
        $space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'p', 'P');
        $user  = new TestUserEntity('u-1');
        foreach ([$space, $page, $user] as $entity) {
            $this->host->persist($entity);
        }
        $this->host->flush();
        // The application's manager forgets them: the page is a saved row nobody holds, the user a detached object.
        $this->host->clear();

        $revision = new WikiPageRevision($page, 2, '<p>x</p>', $user);
        $this->private->persist($revision);
        $this->private->flush();

        self::assertNotSame($page, $revision->getPage(), 'the saved page became the private manager\'s reference');
        self::assertNotSame($user, $revision->getAuthor());
        self::assertSame(1, (int) $this->host->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_page_revisions WHERE author_id = ?', ['u-1']));
    }

    public function testAHostObjectOfAnotherClassIsRefused(): void
    {
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $page     = new WikiPage($space, 'p', 'P');
        $bookmark = new TestHostBookmark('b-1');
        $this->host->persist($bookmark);
        $this->host->flush();
        $this->private->persist($space);
        $this->private->persist($page);
        // @phpstan-ignore argument.type (a host entity of the wrong class on purpose)
        $this->private->persist(new WikiPageRevision($page, 1, '<p>x</p>', $bookmark));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('it holds a ' . TestHostBookmark::class . ', not a ' . TestUserEntity::class);
        $this->private->flush();
    }

    public function testAReferenceDoctrineCannotGiveIsRefused(): void
    {
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getReference')->willReturn(null);
        $method = new ReflectionMethod(WikiEntityManager::class, 'hostReference');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('no ' . TestUserEntity::class . ' reference exists');
        $method->invoke($this->private, $manager, WikiPageRevision::class, 'author', TestUserEntity::class, new TestUserEntity('u-2'), ['id' => 'u-2']);
    }

    public function testAttachLeavesWhatItDoesNotOwnAlone(): void
    {
        $object = new stdClass();
        self::assertSame($object, $this->private->attach($object));
        $new = new WikiSpace('new', 'New', WikiSpaceOwnerScope::Team, 'team-1');
        self::assertSame($new, $this->private->attach($new), 'a new row is returned as it is');
    }
}
