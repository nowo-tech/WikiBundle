<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * 1.40: the title finder of the link picker (spaces by id, titles only, literal and case-insensitive, prefix matches
 * first) and the current HTML of pages for section labels, each in one query; on SQLite, MySQL and PostgreSQL.
 */
final class WikiPageTitleFinderTest extends TestCase
{
    public function testTitlesAndCurrentHtml(): void
    {
        $em    = WikiTestDatabase::create();
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $other = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');
        $em->persist($space);
        $em->persist($other);
        $pages = [];
        foreach ([[$space, 'a', 'Deploy notes'], [$space, 'b', 'How to DEPLOY'], [$space, 'c', 'Deploy x_y'], [$other, 'd', 'Deploy ops'], [$space, 'e', 'Archived deploy']] as [$in, $slug, $title]) {
            $pages[$slug] = new WikiPage($in, $slug, $title);
            $em->persist($pages[$slug]);
        }
        $pages['e']->archive();
        $revision = new WikiPageRevision($pages['a'], 1, '<h2 id="nowo-h-x">X</h2>', null);
        $pages['a']->setCurrentRevision($revision);
        $em->persist($revision);
        $em->flush();
        $repository = new DoctrineOrmWikiPageRepository($em);
        $titles     = static fn (array $found): array => array_map(static fn (WikiPage $page): string => $page->getTitle(), $found);

        self::assertSame(['Deploy notes', 'Deploy x_y', 'How to DEPLOY'], $titles($repository->findByTitle([$space->getId()], 'deploy', 10)));
        self::assertSame(['Deploy x_y'], $titles($repository->findByTitle([$space->getId()], 'x_', 10)));
        self::assertSame([], $repository->findByTitle([$space->getId()], 'x%', 10));
        self::assertSame(['Deploy notes', 'Deploy ops', 'Deploy x_y'], $titles($repository->findByTitle([$space->getId(), $other->getId()], 'deploy ', 10)));
        self::assertCount(1, $repository->findByTitle([$space->getId()], 'deploy', 1));
        self::assertCount(3, $repository->findByTitle([$space->getId()], '', 10));
        self::assertSame([], $repository->findByTitle([], 'deploy', 10));
        self::assertSame([], $repository->findByTitle([$space->getId()], 'deploy', 0));

        self::assertSame([$pages['a']->getId() => '<h2 id="nowo-h-x">X</h2>'], $repository->currentHtml([$pages['a']->getId(), $pages['b']->getId()]));
        self::assertSame([], $repository->currentHtml([]));
        $em->getConnection()->close();
    }
}
