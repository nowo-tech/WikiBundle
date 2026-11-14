<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Ddd;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use LogicException;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Service\WikiPageOperations;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;
use function strtoupper;

use const PHP_INT_MAX;

/**
 * 1.36.0 (REQ-ARCH-001 rule 1): the 1.35 "create at a position" ({@see WikiPageMover::createAt()} /
 * {@see WikiPageMover::createNextTo()}) by id — {@see WikiPageOperations::createPageAt()} and
 * {@see WikiPageOperations::createPageNextTo()} take ids and a `WikiUserRef` and return a snapshot, under the same space
 * lock, ordering and depth rules, in both host kinds (a Doctrine `user_class` and users stored by id).
 */
final class WikiPageOperationsPlacementTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    /** @var list<string> */
    private array $saved = [];

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostKinds(): iterable
    {
        yield 'standard host (entity)' => [WikiMetadataListener::USER_REFERENCE_ENTITY];
        yield 'DDD host (id)' => [WikiMetadataListener::USER_REFERENCE_ID];
    }

    #[DataProvider('hostKinds')]
    public function testPagesAreCreatedAtAPlaceById(string $mode): void
    {
        [$ops, $author] = $this->boot($mode, 3);
        $a              = $ops->createPage($this->space->getId(), 'A', 'a', $author);
        $c              = $ops->createPageAt($this->space->getId(), null, PHP_INT_MAX, 'C', 'c', $author);
        $b              = $ops->createPageNextTo($a->id, true, 'B', 'b', $author, 'bee', WikiPageRevision::FORMAT_MARKDOWN);
        $first          = $ops->createPageNextTo($a->id, false, 'First', 'f', $author);
        $child          = $ops->createPageAt($this->space->getId(), $b->id, 0, 'Child', 'x', $author);
        $grandchild     = $ops->createPageNextTo($child->id, true, 'Second child', 'y', $author);

        self::assertSame(['First', 'A', 'B', 'C'], $this->order(null));
        self::assertSame(['Child', 'Second child'], $this->order($b->id));
        self::assertSame('bee', $b->slug);
        self::assertSame($b->id, $child->parentId);
        self::assertSame($b->id, $grandchild->parentId);
        self::assertNull($c->parentId);
        self::assertInstanceOf(WikiPageSnapshot::class, $first);
        self::assertSame($first->position, $this->em->find(WikiPage::class, $first->id)?->getPosition());
        self::assertCount(6, $this->saved, 'the page service raised its events, once per page');

        // tree.max_depth (3): a page below level 3 is refused, nothing written.
        $deep = $ops->createPageAt($this->space->getId(), $child->id, 0, 'Level 3', 'z', $author);
        try {
            $ops->createPageAt($this->space->getId(), $deep->id, 0, 'Level 4', 'z', $author);
            self::fail('too deep');
        } catch (WikiPageMoveException $exception) {
            self::assertSame('too_deep', $exception->reason);
        }
        self::assertCount(7, $this->saved);
    }

    public function testUnknownForeignOrLookAlikeIdsFailClosed(): void
    {
        [$ops, $author] = $this->boot(WikiMetadataListener::USER_REFERENCE_ID, 10);
        $other          = new WikiSpace('other', 'Other', WikiSpaceOwnerScope::Team, 'team-2');
        $this->em->persist($other);
        $this->em->flush();
        $page    = $ops->createPage($this->space->getId(), 'A', 'a', $author);
        $foreign = $ops->createPage($other->getId(), 'F', 'f', $author);
        $cases   = [
            'unknown space'         => static fn (): WikiPageSnapshot => $ops->createPageAt('missing', null, 0, 'T', 'b', $author),
            'parent in other space' => fn (): WikiPageSnapshot => $ops->createPageAt($this->space->getId(), $foreign->id, 0, 'T', 'b', $author),
            'unknown parent'        => fn (): WikiPageSnapshot => $ops->createPageAt($this->space->getId(), 'missing', 0, 'T', 'b', $author),
            'unknown sibling'       => static fn (): WikiPageSnapshot => $ops->createPageNextTo('missing', true, 'T', 'b', $author),
            'look-alike sibling'    => static fn (): WikiPageSnapshot => $ops->createPageNextTo(strtoupper($page->id), true, 'T', 'b', $author),
        ];
        foreach ($cases as $case => $call) {
            try {
                $call();
                self::fail($case);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertCount(2, $this->saved, 'nothing written');
    }

    public function testWithoutThePageTreeThereIsNoPlace(): void
    {
        $this->em    = WikiTestDatabase::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->space);
        $this->em->flush();
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $service    = new WikiPageService($repository, new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $ops        = new WikiPageOperations($service, $repository, new DoctrineOrmWikiSpaceRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em));

        $this->expectException(LogicException::class);
        $ops->createPageAt($this->space->getId(), null, 0, 'T', 'b', WikiUserRef::ofId(TestUserEntity::class, 'ana'));
    }

    /**
     * @return array{WikiPageOperations, WikiUserRef}
     */
    private function boot(string $mode, int $maxDepth): array
    {
        $this->em    = WikiTestDatabase::create(null, $mode);
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->space);
        $author = WikiUserRef::ofId(WikiSecurityUserDto::class, 'ana');
        if ($mode === WikiMetadataListener::USER_REFERENCE_ENTITY) {
            $user = new TestUserEntity('ana');
            $this->em->persist($user);
            $author = new WikiUserRef(TestUserEntity::class, 'ana', 'ana', [], $user);
        }
        $this->em->flush();
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $class      = $mode === WikiMetadataListener::USER_REFERENCE_ID ? WikiSecurityUserDto::class : TestUserEntity::class;
        $users      = new DoctrineWikiUserReferenceResolver($class, new WikiEntityManagerProvider($registry), new WikiUserClassPolicy($class), $mode);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_SAVED, function (WikiPageSavedEvent $event): void {
            $this->saved[] = $event->pageId();
        });
        $repository = new DoctrineOrmWikiPageRepository($this->em);
        $revisions  = new DoctrineOrmWikiPageRevisionRepository($this->em);
        $service    = new WikiPageService($repository, $revisions, new WikiSlugger(), new WikiHtmlSanitizer(), $dispatcher, users: $users);

        return [new WikiPageOperations($service, $repository, new DoctrineOrmWikiSpaceRepository($this->em), $revisions, new WikiPageMover($repository, $repository, $dispatcher, $maxDepth)), $author];
    }

    /**
     * @return list<string> the titles of the parent's active children, in order
     */
    private function order(?string $parentId): array
    {
        $query = $this->em->createQueryBuilder()->select('p.title AS title')->from(WikiPage::class, 'p')
            ->where('p.archivedAt IS NULL')->orderBy('p.position', 'ASC');
        $parentId === null ? $query->andWhere('p.parent IS NULL') : $query->andWhere('IDENTITY(p.parent) = :parent')->setParameter('parent', $parentId);

        return array_map(static fn (array $row): string => (string) $row['title'], $query->getQuery()->getArrayResult());
    }
}
