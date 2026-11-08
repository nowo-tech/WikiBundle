<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageMetadata;
use Nowo\WikiBundle\Repository\WikiPageMetadataQuery;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_filter;
use function array_map;
use function count;
use function str_starts_with;

/**
 * 1.24 (spec 218 FR-019): page lists read the current revision's metadata in one query, never its body.
 */
final class WikiPageMetadataFinderTest extends TestCase
{
    private EntityManager $em;

    private RecordingLogger $sql;

    private WikiSpace $space;

    private DoctrineOrmWikiPageRepository $repository;

    private WikiPageService $pages;

    private TestUserEntity $user;

    protected function setUp(): void
    {
        $this->sql   = new RecordingLogger();
        $this->em    = WikiTestDatabase::create($this->sql);
        $this->user  = new TestUserEntity('u-1');
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $other       = new WikiSpace('ops', 'Operations', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->user);
        $this->em->persist($this->space);
        $this->em->persist($other);
        $this->em->flush();
        $this->repository = new DoctrineOrmWikiPageRepository($this->em);
        $this->pages      = new WikiPageService($this->repository, new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher());
        $this->pages->create($other, 'Elsewhere', 'x', $this->user);
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testFiltersByArchivedParentTopLevelAndDate(): void
    {
        $guide = $this->pages->create($this->space, 'Guide', 'one', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->pages->saveRevision($guide, 'Guide', 'two', $this->user, WikiPageRevision::FORMAT_MARKDOWN);
        $child = $this->pages->create($this->space, 'Install', 'x', $this->user, $guide);
        $old   = $this->pages->create($this->space, 'Old', 'x', $this->user);
        $this->pages->archive($old);
        $bare = new WikiPage($this->space, 'bare', 'Bare');
        $this->em->persist($bare);
        $this->em->flush();
        $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET updated_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $child->getId()]);
        $this->em->clear();
        $space = $this->em->find(WikiSpace::class, $this->space->getId());
        self::assertInstanceOf(WikiSpace::class, $space);

        $active = $this->repository->pageMetadata($space, new WikiPageMetadataQuery());
        self::assertEqualsCanonicalizing(['Guide', 'Install', 'Bare'], $this->titles($active));
        $byTitle = [];
        foreach ($active as $row) {
            $byTitle[$row->page->getTitle()] = $row;
        }
        self::assertSame(2, $byTitle['Guide']->revisionNumber);
        self::assertSame('u-1', $byTitle['Guide']->authorId);
        self::assertInstanceOf(DateTimeImmutable::class, $byTitle['Guide']->revisedAt);
        self::assertNull($byTitle['Bare']->revisionNumber);
        self::assertNull($byTitle['Bare']->revisedAt);
        self::assertNull($byTitle['Bare']->authorId);

        self::assertSame(['Old'], $this->titles($this->repository->pageMetadata($space, new WikiPageMetadataQuery(archived: true))));
        self::assertSame(['Install'], $this->titles($this->repository->pageMetadata($space, new WikiPageMetadataQuery(parentId: $guide->getId()))));
        self::assertEqualsCanonicalizing(['Guide', 'Bare'], $this->titles($this->repository->pageMetadata($space, new WikiPageMetadataQuery(topLevel: true))));
        self::assertEqualsCanonicalizing(['Guide', 'Bare'], $this->titles($this->repository->pageMetadata($space, new WikiPageMetadataQuery(updatedSince: new DateTimeImmutable('2021-01-01')))));
    }

    public function testOneQueryWhateverTheNumberOfPages(): void
    {
        for ($i = 0; $i < 25; ++$i) {
            $this->pages->create($this->space, 'Page ' . $i, 'body ' . $i, $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        }
        $this->em->clear();
        $space = $this->em->find(WikiSpace::class, $this->space->getId());
        self::assertInstanceOf(WikiSpace::class, $space);

        $before = $this->queries();
        $rows   = $this->repository->pageMetadata($space, new WikiPageMetadataQuery());
        foreach ($rows as $row) {
            // Reading the metadata loads no revision.
            self::assertIsInt($row->revisionNumber);
        }

        self::assertCount(25, $rows);
        self::assertSame(1, $this->queries() - $before);
    }

    /**
     * @param list<WikiPageMetadata> $rows
     *
     * @return list<string>
     */
    private function titles(array $rows): array
    {
        return array_map(static fn (WikiPageMetadata $row): string => $row->page->getTitle(), $rows);
    }

    private function queries(): int
    {
        return count(array_filter($this->sql->records, static fn (array $record): bool => str_starts_with($record['message'], 'Executing')));
    }
}
