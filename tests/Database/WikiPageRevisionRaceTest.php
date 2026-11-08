<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function microtime;
use function proc_close;
use function proc_open;
use function sort;
use function stream_get_contents;
use function trim;

use const PHP_BINARY;

/**
 * Spec 218 SC-003: two processes save the same page from base revision 1 at the same time. Exactly one gets the new
 * revision, the other a conflict naming it; one revision is added, with the winner's body. On MySQL and PostgreSQL only
 * (a server with row locks): SQLite has none and answers a busy database instead.
 */
final class WikiPageRevisionRaceTest extends TestCase
{
    public function testTwoConcurrentSavesFromOneBaseRevision(): void
    {
        if (!WikiTestDatabase::isServer()) {
            self::markTestSkipped('Needs ' . WikiTestDatabase::ENV . ' (MySQL or PostgreSQL).');
        }

        $em     = WikiTestDatabase::create();
        $author = new TestUserEntity('u-race');
        $space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $em->persist($author);
        $em->persist($space);
        $em->flush();
        $page = (new WikiPageService(new DoctrineOrmWikiPageRepository($em), new DoctrineOrmWikiPageRevisionRepository($em), new WikiSlugger(), new WikiHtmlSanitizer(), new EventDispatcher()))
            ->create($space, 'Runbook', 'base', $author, null, null, WikiPageRevision::FORMAT_MARKDOWN);

        $startAt = (string) (microtime(true) + 1.5);
        $running = [];
        foreach (['first', 'second'] as $body) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/Race/save-worker.php', $page->getId(), 'u-race', $body, $startAt], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            $running[$body] = [$process, $pipes];
        }

        $answers = [];
        foreach ($running as $body => [$process, $pipes]) {
            $output = trim((string) stream_get_contents($pipes[1]));
            $errors = (string) stream_get_contents($pipes[2]);
            self::assertSame(0, proc_close($process), $errors . $output);
            $answers[$body] = $output;
        }

        $sorted = $answers;
        sort($sorted);
        self::assertSame(['conflict 2', 'ok 2'], $sorted, 'Exactly one save wins and the other names its revision.');

        $winner = $answers['first'] === 'ok 2' ? 'first' : 'second';
        $rows   = $em->getConnection()->fetchAllAssociative('SELECT revision_number, content_markdown FROM wiki_page_revisions WHERE page_id = ? ORDER BY revision_number', [$page->getId()]);
        self::assertCount(2, $rows);
        self::assertSame($winner, $rows[1]['content_markdown']);
        $current = $em->getConnection()->fetchOne('SELECT r.content_markdown FROM wiki_pages p JOIN wiki_page_revisions r ON r.id = p.current_revision_id WHERE p.id = ?', [$page->getId()]);
        self::assertSame($winner, $current);
        $em->getConnection()->close();
    }
}
