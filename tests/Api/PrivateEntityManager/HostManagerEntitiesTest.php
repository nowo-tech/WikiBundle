<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\PrivateEntityManager;

use Doctrine\DBAL\Connection;
use LogicException;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Repository\WikiSpaceBatchFinderInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageNotFoundException;
use Nowo\WikiBundle\Space\WikiSpaceNotFoundException;
use Nowo\WikiBundle\Tests\Api\ApiTestCase;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;

use function array_filter;
use function array_keys;
use function assert;
use function count;
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
    protected static array $wikiConfig = ['database' => ['private_entity_manager' => true]];

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
        } catch (LogicException $exception) {
            self::assertStringContainsString('is not stored yet', $exception->getMessage());
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
        $guard = self::getContainer()->get(WikiApiGuard::class);
        assert($guard instanceof WikiApiGuard);
        $wiki = $guard->wiki(new WikiApiPrincipal($this->ana, WikiApiLevel::Full, WikiApiWhere::everything(), 't', 'n'), 'fresh');
        self::assertInstanceOf(WikiSpace::class, $wiki->space);
        self::assertTrue($this->private()->contains($wiki->space), 'the directory\'s space is the wiki\'s own instance');
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

    public function testAPageTheWikiCannotReadIsRefusedAsMissing(): void
    {
        $ghost = new WikiPage($this->eng, 'ghost', 'Ghost');

        $this->expectException(WikiPageNotFoundException::class);
        $this->pages()->saveRevision($ghost, 'Ghost', 'body', $this->ana);
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
