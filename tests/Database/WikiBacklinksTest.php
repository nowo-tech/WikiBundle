<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Backlink\WikiBacklinks;
use Nowo\WikiBundle\Backlink\WikiPageReferenceIndex;
use Nowo\WikiBundle\Backlink\WikiPageReferenceListener;
use Nowo\WikiBundle\Command\WikiBacklinksRebuildCommand;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use Nowo\WikiBundle\QuickAccess\WikiReadableSpaces;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;
use function count;
use function in_array;
use function range;
use function sort;
use function sprintf;

/**
 * 1.45 (`backlinks.enabled`): the backlink index ({@see WikiPageReferenceIndex}, written after every save by
 * {@see WikiPageReferenceListener}), the per-viewer "Linked from" list ({@see WikiBacklinks}) and
 * `wiki:backlinks:rebuild`, on SQLite and — through WIKI_TEST_DATABASE_URL — MySQL 8.4, MariaDB 11.4 and PostgreSQL 16.
 */
final class WikiBacklinksTest extends TestCase
{
    private EntityManager $em;

    private WikiTransactionRunner $runner;

    private WikiPageService $pages;

    private WikiPageReferenceIndex $index;

    private TestUserEntity $author;

    private WikiSpace $eng;

    private WikiSpace $ops;

    private WikiSpace $other;

    /** @var list<string> page slugs the checker hides */
    private array $hidden = [];

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->runner = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $this->author = new TestUserEntity('u-1');
        $this->eng    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->ops    = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-2');
        $this->other  = new WikiSpace('other', 'Other tenant', WikiSpaceOwnerScope::Team, 'team-9');
        foreach ([$this->author, $this->eng, $this->ops, $this->other] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->index = new WikiPageReferenceIndex($this->runner, new WikiMarkdownReferenceExtractor());
        $dispatcher  = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::PAGE_SAVED, (new WikiPageReferenceListener($this->index))->onPageSaved(...));
        $this->pages = LoadReadinessFactory::pages($this->em, $dispatcher);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testEverySaveIndexesTheCurrentRevision(): void
    {
        $target = $this->page($this->eng, 'Target', 'nothing');
        $other  = $this->page($this->eng, 'Other', 'nothing');
        $source = $this->page($this->eng, 'Source', sprintf("See [[wiki:%s]] and [[wiki:%s#intro]], itself [[wiki:%s]]\n\n```\n[[wiki:%s]]\n```", $target->getId(), $other->getId(), 'SELF', $target->getId()));
        self::assertSame($this->sorted([$target->getId(), $other->getId()]), $this->targets($source), 'code is not a link, duplicates collapse');

        // An edit replaces the links (one kept, one removed, one added, the page itself ignored).
        $third = $this->page($this->eng, 'Third', 'nothing');
        $this->pages->saveRevision($source, 'Source', sprintf('[[wiki:%s]] [[wiki:%s]] [[wiki:%s]]', $target->getId(), $third->getId(), $source->getId()), $this->author, WikiPageRevision::FORMAT_MARKDOWN);
        self::assertSame($this->sorted([$target->getId(), $third->getId()]), $this->targets($source));

        // A restore of the first revision brings its links back.
        $first = $this->em->getRepository(WikiPageRevision::class)->findOneBy(['page' => $source, 'revisionNumber' => 1]);
        self::assertInstanceOf(WikiPageRevision::class, $first);
        $this->pages->restore($source, $first, $this->author);
        self::assertSame($this->sorted([$target->getId(), $other->getId()]), $this->targets($source));

        // A page that is gone is skipped.
        self::assertFalse($this->index->reindex('00000000-0000-4000-8000-000000000000'));
        // An HTML page has no references.
        self::assertTrue($this->index->reindex($this->htmlPage()->getId()));
        self::assertFalse($this->index->inTransaction());
    }

    public function testLinkedFromListsOnlyReadableLiveSourcesBeforeTheCut(): void
    {
        $target = $this->page($this->eng, 'Target', 'nothing');
        $link   = sprintf('[[wiki:%s]]', $target->getId());
        $parent = $this->page($this->eng, 'Hidden parent', 'nothing');
        $this->page($this->eng, 'Below hidden', $link, $parent);
        $this->page($this->eng, 'Zulu', $link);
        $this->page($this->eng, 'alpha', $link);
        $this->page($this->ops, 'Ops source', $link);
        $this->page($this->other, 'Other tenant', $link);
        $archived = $this->page($this->eng, 'Archived', $link);
        $archived->archive();
        $this->em->flush();
        $this->hidden = ['hidden-parent'];

        self::assertSame(['alpha', 'Ops source', 'Zulu'], $this->titles($this->backlinks()->sources($this->author, $target)));
        // The limit counts readable sources only.
        self::assertCount(2, $this->backlinks(limit: 2)->sources($this->author, $target));
        // The candidate bound: only so many index rows are read.
        self::assertLessThanOrEqual(1, count($this->backlinks(candidates: 1)->sources($this->author, $target)));
        // No readable space: nothing.
        self::assertSame([], $this->backlinks(canList: false)->sources($this->author, $target));
    }

    public function testTheScanReadsSeveralBatches(): void
    {
        $target = $this->page($this->eng, 'Target', 'nothing');
        $link   = sprintf('[[wiki:%s]]', $target->getId());
        for ($i = 0; $i < 105; ++$i) {
            $this->page($this->eng, 'Hidden ' . $i, $link);
        }
        $this->page($this->eng, 'Visible', $link);
        $this->hidden = array_map(static fn (int $i): string => 'hidden-' . $i, range(0, 104));

        // 106 rows: a full batch of 100, then the rest; the hidden ones are read but never listed.
        self::assertSame(['Visible'], $this->titles($this->backlinks(candidates: 500)->sources($this->author, $target)));
    }

    public function testTheRebuildCommandIndexesEveryPage(): void
    {
        $target = $this->page($this->eng, 'Target', 'nothing');
        $source = $this->page($this->eng, 'Source', sprintf('[[wiki:%s]]', $target->getId()));
        $this->page($this->ops, 'Plain', 'nothing');
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_page_references');

        $tester = new CommandTester(new WikiBacklinksRebuildCommand($this->index));
        self::assertSame(Command::SUCCESS, $tester->execute(['--batch-size' => '2']));
        self::assertStringContainsString('3 page(s) indexed', $tester->getDisplay());
        self::assertSame([$target->getId()], $this->targets($source));

        self::assertSame(Command::INVALID, $tester->execute(['--batch-size' => '0']));
        self::assertSame(Command::INVALID, $tester->execute(['--batch-size' => 'x']));
    }

    /** A lock conflict of the index write (simulated on SQLite): logged; inside a caller's transaction it propagates. */
    public function testALockConflictOfTheIndexIsLoggedOrPropagatedWhenNested(): void
    {
        if (WikiTestDatabase::isServer()) {
            self::markTestSkipped('SQLite simulation; the servers run the real lock conflict in WikiQuickAccessRaceTest.');
        }
        $target = $this->page($this->eng, 'Target', 'nothing');
        $source = $this->page($this->eng, 'Source', 'nothing');
        $this->em->getConnection()->executeStatement("CREATE TRIGGER wk_ref_locked BEFORE INSERT ON wiki_page_references BEGIN SELECT RAISE(ABORT, 'database is locked'); END");
        $logger   = new RecordingLogger();
        $listener = new WikiPageReferenceListener($this->index, $logger);
        $revision = new WikiPageRevision($source, 9, '', $this->author, sprintf('[[wiki:%s]]', $target->getId()));
        $this->em->persist($revision);
        $source->setCurrentRevision($revision);
        $this->em->flush();

        $listener->onPageSaved(new WikiPageSavedEvent($source, $revision));
        self::assertStringContainsString('backlink index of a saved page was not updated', $logger->lines()[0] ?? '');

        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $listener->onPageSaved(new WikiPageSavedEvent($source, $revision));
            self::fail('Expected the lock conflict.');
        } catch (TransientStorageFailure) {
            self::assertTrue($connection->isTransactionActive());
        } finally {
            $connection->rollBack();
        }
    }

    private function backlinks(int $limit = 20, int $candidates = 200, bool $canList = true): WikiBacklinks
    {
        $roles = $this->createStub(WikiAccessCheckerInterface::class);
        $roles->method('canList')->willReturn($canList);
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$this->eng, $this->ops]);
        $checker = new CallbackSpaceAccessChecker(fn (WikiAction $action, WikiSpace $space, ?WikiPage $page): bool => $action === WikiAction::View && (!$page instanceof WikiPage || !in_array($page->getSlug(), $this->hidden, true)));

        return new WikiBacklinks($this->runner, new WikiReadableSpaces($roles, $resolver, $checker), $limit, $candidates);
    }

    private function page(WikiSpace $space, string $title, string $markdown, ?WikiPage $parent = null): WikiPage
    {
        return $this->pages->create($space, $title, $markdown, $this->author, $parent, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    private function htmlPage(): WikiPage
    {
        return $this->pages->create($this->eng, 'Html', '<p>html</p>', $this->author);
    }

    /**
     * @return list<string>
     */
    private function targets(WikiPage $source): array
    {
        /* @var list<string> */
        return $this->em->getConnection()->fetchFirstColumn('SELECT target_page_id FROM wiki_page_references WHERE source_page_id = ? ORDER BY target_page_id', [$source->getId()]);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    /**
     * @param list<WikiPage> $pages
     *
     * @return list<string>
     */
    private function titles(array $pages): array
    {
        return array_map(static fn (WikiPage $page): string => $page->getTitle(), $pages);
    }
}
