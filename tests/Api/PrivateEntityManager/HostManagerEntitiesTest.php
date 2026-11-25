<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\PrivateEntityManager;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Repository\WikiSpaceBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageDuplicator;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageMoveException;
use Nowo\WikiBundle\Service\WikiPageMover;
use Nowo\WikiBundle\Space\WikiSpaceNotFoundException;
use Nowo\WikiBundle\Tests\Api\ApiTestCase;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Trash\WikiTrashService;
use ReflectionProperty;

use function array_filter;
use function array_keys;
use function assert;
use function count;
use function sprintf;
use function str_contains;

/**
 * 1.55.2 (Beacon, `database.private_entity_manager: true`): spaces and pages the host loaded through the APPLICATION's
 * manager (a deprecated directory or locator, or host code calling the page services) are read again by id through
 * the wiki's private manager before anything is attached to them, locked or saved.
 *
 * Before 1.55.2: a space the directory persisted without flushing failed the first write with Doctrine's "A new entity
 * was found through the relationship 'WikiPage#space'", and every page write with a host-loaded page with "Entity
 * WikiPage@… is not managed" (the page lock refreshes it through the private manager).
 */
final class HostManagerEntitiesTest extends ApiTestCase
{
    /** @var array<string, mixed> */
    protected static array $wikiConfig = ['database' => ['private_entity_manager' => true], 'trash' => ['enabled' => true]];

    /**
     * The trash table of this class's configuration is unknown to the next classes' schema tools (their drop would leave
     * it, and its foreign key, behind on MySQL / MariaDB / PostgreSQL): drop every table.
     */
    protected function tearDown(): void
    {
        $this->private()->clear();
        $this->em()->clear();
        (new SchemaTool($this->em()))->dropDatabase();
        parent::tearDown();
    }

    public function testANewPageAttachesToTheWikisOwnInstanceOfAHostLoadedSpace(): void
    {
        $private   = $this->private();
        $hostSpace = $this->em()->find(WikiSpace::class, $this->eng->getId());
        self::assertInstanceOf(WikiSpace::class, $hostSpace);
        self::assertFalse($private->contains($hostSpace), 'precondition: the application\'s instance');

        $page = $this->pages()->create($hostSpace, 'Host space', 'body', $this->ana, null, null, WikiPageRevision::FORMAT_MARKDOWN);

        self::assertNotSame($hostSpace, $page->getSpace());
        self::assertTrue($private->contains($page->getSpace()), 'the page points at the wiki\'s own instance');
        self::assertSame($this->eng->getId(), $page->getSpace()->getId());
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM wiki_pages WHERE space_id = ?', [$this->eng->getId()]));
    }

    public function testASpaceTheHostPersistedWithoutFlushingIsRefusedBeforeAnythingIsWritten(): void
    {
        // What raised "A new entity was found through the relationship 'WikiPage#space'" up to 1.55.1.
        $draft = new WikiSpace('draft', 'Draft', WikiSpaceOwnerScope::Team, 'owner-1');
        $this->em()->persist($draft);

        try {
            $this->pages()->create($draft, 'Lost', 'body', $this->ana);
            self::fail('a space the wiki cannot read is refused');
        } catch (WikiSpaceNotFoundException) {
            // 2.0 (W1-2): a space your manager holds that the wiki cannot load is a missing space.
        }
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM wiki_pages', []));
        self::assertTrue($this->em()->getUnitOfWork()->isScheduledForInsert($draft), 'the application\'s pending work is left alone');
        $this->em()->detach($draft);
    }

    public function testTheApiProvisionsAndWritesThroughTheWikisOwnInstance(): void
    {
        ApiTestHost::$wikis['fresh']           = ['name' => 'Fresh', 'unopened' => true];
        ApiTestHost::$grants['u-ana']['fresh'] = ApiTestHost::all();

        $created = $this->call('POST', '/wikis/fresh/pages', self::ANA, ['title' => 'First', 'content_markdown' => 'Hello']);

        self::assertSame(201, $created->getStatusCode(), (string) $created->getContent());
        self::assertSame(['fresh'], ApiTestHost::$provisioned);
        // 2.0: the page services load the directory's space again by id in the private manager (W1-2).
        $page = $this->private()->find(WikiPage::class, (string) $this->json($created)['id']);
        self::assertInstanceOf(WikiPage::class, $page);
        self::assertSame('fresh', $page->getSpace()->getSlug());
    }

    public function testHostLoadedPagesAreWrittenThroughTheWikisOwnInstance(): void
    {
        $page  = $this->page($this->eng, 'Linked', 'one');
        $first = $page->getCurrentRevision();
        $host  = $this->em()->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $host);
        self::assertInstanceOf(WikiPageRevision::class, $first);
        self::assertFalse($this->private()->contains($host), 'precondition: the application\'s instance');

        $service = $this->pages();
        self::assertSame(2, $service->saveRevision($host, 'Linked', 'two', $this->ana, WikiPageRevision::FORMAT_MARKDOWN)->getRevisionNumber());
        self::assertSame(3, $service->saveRevision($host, 'Linked', 'three', $this->ana, WikiPageRevision::FORMAT_MARKDOWN, 2)->getRevisionNumber());
        self::assertSame(4, $service->restore($host, $first, $this->ana)->getRevisionNumber());
        self::assertSame(5, $service->convertToMarkdown($host, 'five', $this->ana)->getRevisionNumber());
        $service->archive($host, $this->ana);
        $service->unarchive($host, $this->ana);

        $links = self::getContainer()->get(WikiPageLinkService::class);
        assert($links instanceof WikiPageLinkService);
        $link = $links->link($host, 'ticket', 'T-1', $this->ana);
        self::assertTrue($this->private()->contains($link->getPage()));
        self::assertTrue($links->unlink($host, 'ticket', 'T-1', $this->ana));

        $icons = self::getContainer()->get(WikiPageIconService::class);
        assert($icons instanceof WikiPageIconService);
        self::assertFalse($icons->setIcon($host, null), 'no icon before, none after: nothing written');

        self::assertSame(5, $this->rows('SELECT COUNT(*) FROM wiki_page_revisions WHERE page_id = ?', [$page->getId()]));
        self::assertSame(0, $this->rows('SELECT COUNT(*) FROM wiki_pages WHERE id = ? AND archived_at IS NOT NULL', [$page->getId()]));
    }

    public function testTheFlushStillAdoptsAHostSpaceThatReachesTheWikiAnotherWay(): void
    {
        // The safety net under the by-id reads: a wiki row built directly on the private manager with a stored space of
        // the application's manager is flushed against the private manager's reference to it.
        $hostSpace = $this->em()->find(WikiSpace::class, $this->eng->getId());
        self::assertInstanceOf(WikiSpace::class, $hostSpace);
        $private = $this->private();
        $page    = new WikiPage($hostSpace, 'direct', 'Direct');
        $private->persist($page);
        $private->flush();

        self::assertNotSame($hostSpace, $page->getSpace());
        self::assertSame(1, $this->rows('SELECT COUNT(*) FROM wiki_pages WHERE slug = ?', ['direct']));
    }

    public function testTreeDuplicateAndTrashWritesTakeHostLoadedEntities(): void
    {
        // Audit ZBO-2: the mover, the duplicator and the trash read the host's instances again by id too.
        $parent = $this->page($this->eng, 'Parent', 'p');
        $child  = $this->page($this->eng, 'Child', 'c');
        $host   = fn (WikiPage $page): WikiPage => $this->em()->find(WikiPage::class, $page->getId()) ?? throw new LogicException('missing');
        $space  = $this->em()->find(WikiSpace::class, $this->eng->getId());
        self::assertInstanceOf(WikiSpace::class, $space);
        $mover = self::getContainer()->get(WikiPageMover::class);
        assert($mover instanceof WikiPageMover);

        self::assertTrue($mover->move($host($child), $host($parent), 0, $this->ana)->moved);
        self::assertSame($parent->getId(), $this->connection()->fetchOne('SELECT parent_id FROM wiki_pages WHERE id = ?', [$child->getId()]));
        $placed = $mover->createAt($space, $host($parent), 0, fn (): WikiPage => $this->pages()->create($space, 'First', 'f', $this->ana, $host($parent)));
        self::assertTrue($this->private()->contains($placed->page->getSpace()));
        $next = $mover->createNextTo($space, $host($child), true, fn (): WikiPage => $this->pages()->create($space, 'Next', 'n', $this->ana, $host($parent)));
        self::assertSame($parent->getId(), $next->page->getParent()?->getId());

        $duplicator = self::getContainer()->get(WikiPageDuplicator::class);
        assert($duplicator instanceof WikiPageDuplicator);
        $copy = $duplicator->duplicate($host($child), 'Child copy', $this->ana);
        self::assertTrue($this->private()->contains($copy->page));

        $trash = self::getContainer()->get(WikiTrashService::class);
        assert($trash instanceof WikiTrashService);
        $batch = $trash->delete($host($child), $this->ana);
        self::assertNotNull($trash->restore($space, $batch, $this->ana));
        $again = $trash->delete($host($child), $this->ana);
        self::assertGreaterThan(0, $trash->purge($space, $again, $this->ana));

        $ghost = new WikiPage($this->eng, 'ghost', 'Ghost');
        try {
            $mover->move($ghost, null, 0);
            self::fail('a page the wiki cannot read is gone');
        } catch (WikiPageMoveException $exception) {
            self::assertSame('page_missing', $exception->reason);
        }
        try {
            $mover->move($host($parent), $ghost, 0);
            self::fail('a parent the wiki cannot read is gone');
        } catch (WikiPageMoveException $exception) {
            self::assertSame('parent_missing', $exception->reason);
        }
    }

    public function testADetachedSpaceIsReadAgainById(): void
    {
        // Audit ZBO-5: an instance no manager holds (detached, e.g. kept across requests) is read again by id.
        $this->em()->clear();
        $detached = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'owner-1');
        (new ReflectionProperty(WikiSpace::class, 'id'))->setValue($detached, $this->eng->getId());

        $page = $this->pages()->create($detached, 'Detached', 'd', $this->ana);
        self::assertNotSame($detached, $page->getSpace());
    }

    public function testTheMoverAnswersAHostPageThatNoLongerExistsAsMissing(): void
    {
        $page = $this->page($this->eng, 'Gone soon', 'g');
        $host = $this->em()->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $host);
        $this->connection()->executeStatement('DELETE FROM wiki_pages WHERE id = ?', [$page->getId()]);
        $this->private()->clear();
        $mover = self::getContainer()->get(WikiPageMover::class);
        assert($mover instanceof WikiPageMover);

        try {
            $mover->move($host, null, 0);
            self::fail('a page of yours that no longer exists is gone');
        } catch (WikiPageMoveException $exception) {
            self::assertSame('page_missing', $exception->reason);
        }
    }

    public function testCreateInSpaceReadsTheSpaceById(): void
    {
        $parent = $this->page($this->eng, 'Parent', 'p');
        $host   = $this->em()->find(WikiPage::class, $parent->getId());
        self::assertInstanceOf(WikiPage::class, $host);

        $child = $this->pages()->createInSpace($this->eng->getId(), 'Child', 'c', $this->ana, $host, 'child', WikiPageRevision::FORMAT_MARKDOWN);

        self::assertTrue($this->private()->contains($child->getSpace()));
        $childParent = $child->getParent();
        self::assertInstanceOf(WikiPage::class, $childParent);
        self::assertTrue($this->private()->contains($childParent), 'the host\'s parent instance is read again too');
        self::assertSame('child', $child->getSlug());

        $this->expectException(WikiSpaceNotFoundException::class);
        $this->pages()->createInSpace('00000000-0000-4000-8000-000000000000', 'Nowhere', 'x', $this->ana);
    }

    public function testBatchAndNoRefreshReadsAvoidOneQueryPerSpace(): void
    {
        $ids = [$this->eng->getId(), $this->ops->getId()];
        for ($i = 0; $i < 6; ++$i) {
            $space = new WikiSpace('extra-' . $i, 'Extra ' . $i, WikiSpaceOwnerScope::Team, 'owner-1');
            $this->em()->persist($space);
            $ids[] = $space->getId();
        }
        $this->em()->flush();
        $repository = self::getContainer()->get(WikiSpaceRepositoryInterface::class);
        self::assertInstanceOf(WikiSpaceBatchFinderInterface::class, $repository);
        $batch = self::getContainer()->get(WikiSpaceBatchFinderInterface::class);
        self::assertSame($repository, $batch, 'the batch reads are autowirable');

        $found = $this->queries(static fn (): array => $batch->findByIds([...$ids, $ids[0], '00000000-0000-4000-8000-000000000000']), $queries);
        self::assertSame($ids, array_keys($found), 'every known id once, in the asked order');
        self::assertSame(1, $queries, 'one query for eight spaces');
        self::assertSame([], $batch->findByIds([]));
        // Audit ZBO-4: one statement per 500 ids (driver placeholder limits).
        $many = [];
        for ($i = 0; $i < DoctrineOrmWikiSpaceRepository::FIND_BY_IDS_CHUNK; ++$i) {
            $many[] = sprintf('00000000-0000-4000-8000-%012d', $i);
        }
        $found = $this->queries(static fn (): array => $batch->findByIds([...$many, $ids[1]]), $queries);
        self::assertSame([$ids[1]], array_keys($found));
        self::assertSame(2, $queries);

        // The batch read is fresh: a rename committed behind the manager's back is seen.
        $this->connection()->executeStatement('UPDATE wiki_spaces SET name = ? WHERE id = ?', ['Renamed', $ids[0]]);
        self::assertSame('Renamed', $batch->findByIds([$ids[0]])[$ids[0]]->getName());

        // No refresh: a held space costs no query (and may be stale, read paths only).
        $this->connection()->executeStatement('UPDATE wiki_spaces SET name = ? WHERE id = ?', ['Stale', $ids[0]]);
        $held = $this->queries(static fn (): ?WikiSpace => $batch->findByIdWithoutRefresh($ids[0]), $queries);
        self::assertSame(0, $queries);
        self::assertSame('Renamed', $held?->getName(), 'documented: the identity map\'s state');
        self::assertNull($batch->findByIdWithoutRefresh('00000000-0000-4000-8000-000000000000'));

        // findById keeps its meaning: always the database's state, one query.
        $fresh = $this->queries(static fn (): ?WikiSpace => $repository->findById($ids[0]), $queries);
        self::assertSame('Stale', $fresh?->getName());
        self::assertSame(1, $queries);
        $this->private()->clear();
        $loaded = $this->queries(static fn (): ?WikiSpace => $repository->findById($ids[1]), $queries);
        self::assertSame($ids[1], $loaded?->getId());
        self::assertSame(1, $queries, 'a space the manager does not hold: loaded once, not loaded then refreshed');
        self::assertNull($repository->findById('00000000-0000-4000-8000-000000000000'));
    }

    /**
     * @template T
     *
     * @param callable(): T $read
     *
     * @param-out int $queries
     *
     * @return T
     */
    private function queries(callable $read, ?int &$queries): mixed
    {
        $logger = self::getContainer()->get('logger');
        assert($logger instanceof RecordingLogger);
        $logger->records = [];
        $result          = $read();
        $queries         = count(array_filter($logger->records, static fn (array $record): bool => str_contains($record['message'], 'Executing')));

        return $result;
    }

    private function private(): WikiEntityManager
    {
        $private = $this->wikiEm();
        self::assertInstanceOf(WikiEntityManager::class, $private);

        return $private;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);

        return $connection;
    }

    /**
     * @param list<string> $params
     */
    private function rows(string $sql, array $params): int
    {
        return (int) $this->connection()->fetchOne($sql, $params);
    }
}
