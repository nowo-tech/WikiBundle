<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * 1.38: the page icon on a real database, through the bundle's runner (docs/HIGH-LOAD.md): the page row is locked and
 * read again, so a page archived by another request meanwhile is refused and the last write wins. SQLite by default,
 * MySQL / PostgreSQL with WIKI_TEST_DATABASE_URL.
 */
final class WikiPageIconServiceTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $author;

    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->author = new TestUserEntity('u-icon');
        $this->space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->author);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testTheIconIsStoredClearedAndReadBack(): void
    {
        $page  = $this->page();
        $icons = $this->icons($this->em);

        self::assertTrue($icons->setIcon($page, 'tabler:rocket'));
        self::assertFalse($icons->setIcon($page, 'tabler:rocket'), 'The same value writes nothing.');
        self::assertSame('tabler:rocket', $this->stored($page));

        $snapshot = $icons->setPageIcon($page->getId(), '');
        self::assertNull($snapshot->icon);
        self::assertNull($this->stored($page));
    }

    public function testANotAllowedIconIsRefusedBeforeAnyWrite(): void
    {
        $page  = $this->page();
        $icons = $this->icons($this->em);

        foreach (['bi:rocket', 'tabler:Rocket', '<script>', 'tabler:rocket"onmouseover="x', "tabler:rocket\n", '🚀', 'tabler:' . str_repeat('a', 64)] as $bad) {
            try {
                $icons->setIcon($page, $bad);
                self::fail('Refused: ' . $bad);
            } catch (InvalidArgumentException $refused) {
                self::assertStringContainsString('Invalid page icon', $refused->getMessage());
            }
        }
        self::assertNull($this->stored($page));
    }

    public function testAPageArchivedMeanwhileIsRefused(): void
    {
        $page = $this->page();
        $peer = LoadReadinessFactory::peer($this->em);
        LoadReadinessFactory::pages($peer)->archive($this->peerPage($peer, $page));

        try {
            $this->icons($this->em)->setIcon($page, 'tabler:rocket');
            self::fail('An archived page must be refused.');
        } catch (InvalidArgumentException $refused) {
            self::assertSame('Archived pages cannot be edited.', $refused->getMessage());
        }
        self::assertNull($this->stored($page));
    }

    public function testTheLastWriteWinsOverAnotherRequest(): void
    {
        $page = $this->page();
        $peer = LoadReadinessFactory::peer($this->em);
        $this->icons($peer)->setIcon($this->peerPage($peer, $page), 'tabler:book');

        // This request loaded the page before the other write: it reads the row again under the lock.
        self::assertTrue($this->icons($this->em)->setIcon($page, 'tabler:rocket'));
        self::assertSame('tabler:rocket', $this->stored($page));
        // Setting the value the other request already wrote changes nothing.
        $this->icons($peer)->setIcon($this->peerPage($peer, $page), 'tabler:rocket');
        self::assertSame('tabler:rocket', $this->stored($page));
    }

    public function testAPageDeletedMeanwhileIsRefused(): void
    {
        $page = $this->page();
        $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET current_revision_id = NULL WHERE id = ?', [$page->getId()]);
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_page_revisions WHERE page_id = ?', [$page->getId()]);
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_pages WHERE id = ?', [$page->getId()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The page no longer exists.');
        $this->icons($this->em)->setIcon($page, 'tabler:rocket');
    }

    public function testUnknownPageIdsAndIconsUnknownToTheRendererAreRefused(): void
    {
        $page     = $this->page();
        $renderer = new class {
            public function renderIcon(string $name): string
            {
                return $name === 'tabler:rocket' ? '<svg></svg>' : throw new RuntimeException('missing');
            }
        };
        $icons = new WikiPageIconService(new DoctrineOrmWikiPageRepository($this->em), ['tabler', 'lucide'], new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0), $renderer);

        self::assertSame(['tabler', 'lucide'], $icons->allowedPrefixes());
        self::assertTrue($icons->isAllowed('tabler:rocket'));
        self::assertTrue($icons->isAllowed(null));
        self::assertTrue($icons->isAllowed(''));
        self::assertFalse($icons->isAllowed('lucide:nope'), 'An icon the icon set does not have would break every page that shows it.');
        self::assertSame('tabler:rocket', $icons->setPageIcon($page->getId(), 'tabler:rocket')->icon);

        // A renderer without renderIcon() checks nothing.
        self::assertTrue((new WikiPageIconService(new DoctrineOrmWikiPageRepository($this->em), ['tabler'], null, new stdClass()))->isAllowed('tabler:anything'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown page "' . strtoupper($page->getId()) . '".');
        $icons->setPageIcon(strtoupper($page->getId()), 'tabler:rocket');
    }

    public function testWithoutTheRunnerThePlainWrite(): void
    {
        $page  = $this->page();
        $icons = new WikiPageIconService(new DoctrineOrmWikiPageRepository($this->em));

        self::assertTrue($icons->setIcon($page, 'tabler:rocket'));
        self::assertSame('tabler:rocket', $this->stored($page));
        $page->archive();
        $this->expectException(InvalidArgumentException::class);
        $icons->setIcon($page, null);
    }

    private function icons(EntityManager $em): WikiPageIconService
    {
        return new WikiPageIconService(new DoctrineOrmWikiPageRepository($em), ['tabler'], new WikiTransactionRunner($em, backoffBaseMilliseconds: 0));
    }

    private function page(): WikiPage
    {
        return LoadReadinessFactory::pages($this->em)->create($this->space, 'Runbook', 'base', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    private function peerPage(EntityManager $peer, WikiPage $page): WikiPage
    {
        $found = $peer->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $found);

        return $found;
    }

    private function stored(WikiPage $page): mixed
    {
        return $this->em->getConnection()->fetchOne('SELECT icon FROM wiki_pages WHERE id = ?', [$page->getId()]);
    }
}
