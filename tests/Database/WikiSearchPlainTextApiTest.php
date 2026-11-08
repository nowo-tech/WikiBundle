<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;
use function sort;
use function strcmp;
use function usort;

/**
 * 1.24 (spec 218 FR-017, contract F4): {@see WikiSearchService::searchPlainText()} never matches a page's Markdown
 * source, is case-insensitive on every platform and reads in page-id order.
 */
final class WikiSearchPlainTextApiTest extends TestCase
{
    private const HIDDEN = '0d1c2b3a-4f5e-4a6b-8c7d-9e0f1a2b3c4d';

    private EntityManager $em;

    private WikiSpace $space;

    private WikiPageService $pages;

    private TestUserEntity $user;

    protected function setUp(): void
    {
        $this->em    = WikiTestDatabase::create();
        $this->user  = new TestUserEntity('u-1');
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->user);
        $this->em->persist($this->space);
        $this->em->flush();
        $this->pages = new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testTheMarkdownSourceIsNeverMatched(): void
    {
        $page = $this->pages->create($this->space, 'Links', 'See [[wiki:' . self::HIDDEN . ']] and <!-- secret note -->.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        // The cache as the Milkdown bridge's neutral render writes it: the hidden link is a placeholder, the comment gone.
        $this->em->getConnection()->executeStatement('UPDATE wiki_page_revisions SET content_text = NULL, content_html = ? WHERE page_id = ?', ['<p>See <span>Content not visible</span> and .</p>', $page->getId()]);
        $this->em->clear();
        $search = new WikiSearchService($this->em);

        self::assertSame([], $search->searchPlainText([$this->space()], '0d1c2b3a', 10));
        self::assertSame([], $search->searchPlainText([$this->space()], 'wiki:', 10));
        // The neutral HTML cache still finds what every reader sees.
        self::assertSame(['Links'], $this->titles($search->searchPlainText([$this->space()], 'see', 10)));
    }

    public function testMatchesAreCaseInsensitiveLiteralAndSkipArchivedPages(): void
    {
        $this->pages->create($this->space, 'Backup policy', 'Weekly.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->pages->create($this->space, 'Disks', 'Rotate the BACKUP disks, 100% of them.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $gone = $this->pages->create($this->space, 'Old backup', 'x', $this->user);
        $this->pages->archive($gone);
        $this->em->clear();
        $search = new WikiSearchService($this->em);

        self::assertSame(['Backup policy', 'Disks'], $this->titles($search->searchPlainText([$this->space()], 'Backup', 10)));
        self::assertSame(['Disks'], $this->titles($search->searchPlainText([$this->space()], '100%', 10)));
        self::assertSame([], $search->searchPlainText([$this->space()], '1_0', 10));
        self::assertSame([], $search->searchPlainText([$this->space()], '  ', 10));
        self::assertSame([], $search->searchPlainText([], 'backup', 10));
        self::assertSame([], $search->searchPlainText([$this->space()], 'backup', 0));
        self::assertSame('Rotate the BACKUP disks, 100% of them.', $search->searchPlainText([$this->space()], 'disks', 10)[0]['excerpt']);
    }

    public function testReadsOnInPageIdOrder(): void
    {
        foreach (['One', 'Two', 'Three'] as $title) {
            $this->pages->create($this->space, $title, 'common word', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        }
        $this->em->clear();
        $search = new WikiSearchService($this->em);

        $first = $search->searchPlainText([$this->space()], 'common', 2);
        self::assertCount(2, $first);
        $rest = $search->searchPlainText([$this->space()], 'common', 2, $first[1]['page']->getId());
        self::assertCount(1, $rest);

        $ids    = array_map(static fn (array $hit): string => $hit['page']->getId(), [...$first, ...$rest]);
        $sorted = $ids;
        usort($sorted, strcmp(...));
        self::assertSame($sorted, $ids);
    }

    private function space(): WikiSpace
    {
        $space = $this->em->find(WikiSpace::class, $this->space->getId());
        self::assertInstanceOf(WikiSpace::class, $space);

        return $space;
    }

    /**
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $hits
     *
     * @return list<string>
     */
    private function titles(array $hits): array
    {
        $titles = array_map(static fn (array $hit): string => $hit['page']->getTitle(), $hits);
        sort($titles);

        return $titles;
    }
}
