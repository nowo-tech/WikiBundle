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

/**
 * Spec 218 S0: what the UI's search matches today, on every database. The API gets its own plain-text method (S1);
 * these answers must not change. The last test pins the Markdown fallback of revisions without `content_text`
 * (contract F4), which the API must not inherit.
 */
final class WikiSearchServiceCharacterizationTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiSpace $other;

    private WikiPageService $pages;

    private TestUserEntity $user;

    protected function setUp(): void
    {
        $this->em    = WikiTestDatabase::create();
        $this->user  = new TestUserEntity('u-1');
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->other = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->user);
        $this->em->persist($this->space);
        $this->em->persist($this->other);
        $this->em->flush();
        $this->pages = new WikiPageService(
            new DoctrineOrmWikiPageRepository($this->em),
            new DoctrineOrmWikiPageRevisionRepository($this->em),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testTitlesAndPlainTextMatchLiterallyAndArchivedPagesAreLeftOut(): void
    {
        $this->pages->create($this->space, 'Backup policy', 'Weekly.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->pages->create($this->space, 'Disks', 'Rotate the **backup** disks.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->pages->create($this->space, 'Rates', 'Up 100% this year_now.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $gone = $this->pages->create($this->space, 'Old backup', 'x', $this->user);
        $this->pages->archive($gone);
        $this->pages->create($this->other, 'Backup elsewhere', 'y', $this->user);
        $this->em->clear();

        $search = new WikiSearchService($this->em);
        // `ackup`: LIKE is case-insensitive on SQLite and MySQL (default collation) but not on PostgreSQL, so the
        // pinned queries avoid case; the PostgreSQL difference is a known UI limitation outside this work.
        self::assertSame(['Backup policy', 'Disks'], $this->titles($search->search($this->reload($this->space), 'ackup')));
        self::assertSame([], $this->titles($search->search($this->reload($this->space), '**backup**')));
        self::assertSame(['Rates'], $this->titles($search->search($this->reload($this->space), '100%')));
        self::assertSame(['Rates'], $this->titles($search->search($this->reload($this->space), 'year_now')));
        self::assertSame([], $this->titles($search->search($this->reload($this->space), '')));
        self::assertSame(
            ['Backup elsewhere', 'Backup policy', 'Disks'],
            $this->titles($search->searchAcrossSpaces([$this->reload($this->space), $this->reload($this->other)], 'ackup')),
        );
        self::assertCount(1, $search->search($this->reload($this->space), 'ackup', 1));
    }

    public function testExcerptsComeFromThePlainText(): void
    {
        $this->pages->create($this->space, 'Runbook', 'Restart the **queue** worker first.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->em->clear();

        $hits = (new WikiSearchService($this->em))->search($this->reload($this->space), 'queue');

        self::assertSame('Restart the queue worker first.', $hits[0]['excerpt']);
    }

    public function testARevisionWithoutPlainTextIsMatchedOnItsMarkdownSource(): void
    {
        $page = $this->pages->create($this->space, 'Links', 'See [[wiki:0d1c2b3a-4f5e-4a6b-8c7d-9e0f1a2b3c4d]].', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->em->getConnection()->executeStatement('UPDATE wiki_page_revisions SET content_text = NULL WHERE page_id = ?', [$page->getId()]);
        $this->em->clear();

        // UI behaviour since 1.16 for revisions not backfilled: the source is searched (contract F4: not for the API).
        self::assertSame(['Links'], $this->titles((new WikiSearchService($this->em))->search($this->reload($this->space), '0d1c2b3a')));
    }

    private function reload(WikiSpace $space): WikiSpace
    {
        $found = $this->em->find(WikiSpace::class, $space->getId());
        self::assertInstanceOf(WikiSpace::class, $found);

        return $found;
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
