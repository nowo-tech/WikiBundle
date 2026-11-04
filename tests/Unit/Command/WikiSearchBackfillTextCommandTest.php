<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Command\WikiSearchBackfillTextCommand;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * 1.16: wiki:search:backfill-text fills content_text of revisions written before 1.16.
 */
final class WikiSearchBackfillTextCommandTest extends TestCase
{
    public function testFillsTheMissingTextsInBatches(): void
    {
        $em     = SqliteWikiEntityManager::create();
        $space  = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page   = new WikiPage($space, 'p', 'P');
        $author = new TestUserEntity('ana');
        foreach ([$space, $page, $author] as $entity) {
            $em->persist($entity);
        }
        foreach ([1, 2, 3] as $number) {
            $em->persist(new WikiPageRevision($page, $number, '<p>Body <strong>' . $number . '</strong></p>', $author));
        }
        $em->flush();
        // Written before 1.16: no text yet (two of them).
        $em->getConnection()->executeStatement('UPDATE wiki_page_revisions SET content_text = NULL WHERE revision_number IN (1, 2)');
        $em->clear();

        $tester = new CommandTester(new WikiSearchBackfillTextCommand($em));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('2 revision(s) to fill (dry run', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--batch-size' => '1']));
        self::assertStringContainsString('2 revision(s) read, 2 text(s) written.', $tester->getDisplay());
        self::assertSame(['Body 1', 'Body 2', 'Body 3'], $em->getConnection()->fetchFirstColumn('SELECT content_text FROM wiki_page_revisions ORDER BY revision_number'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('0 revision(s) read, 0 text(s) written.', $tester->getDisplay());

        // --all recomputes every revision; unchanged texts are not counted as written.
        $em->getConnection()->executeStatement("UPDATE wiki_page_revisions SET content_text = 'stale' WHERE revision_number = 3");
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($em);
        $tester = new CommandTester(new WikiSearchBackfillTextCommand(new WikiEntityManagerProvider($registry, 'default')));
        self::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        self::assertStringContainsString('3 revision(s) read, 1 text(s) written.', $tester->getDisplay());
    }

    public function testRefusesABadBatchSize(): void
    {
        $tester = new CommandTester(new WikiSearchBackfillTextCommand(SqliteWikiEntityManager::create()));

        self::assertSame(Command::INVALID, $tester->execute(['--batch-size' => '0']));
        self::assertSame(Command::INVALID, $tester->execute(['--batch-size' => 'many']));
        self::assertStringContainsString('--batch-size must be an integer', $tester->getDisplay());
    }
}
