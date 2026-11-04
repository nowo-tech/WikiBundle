<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\Command\WikiHistoryThinCommand;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiHistoryThinner;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * 1.15 (D-W6): wiki:history:thin thins old revisions; --dry-run only counts.
 */
final class WikiHistoryThinCommandTest extends TestCase
{
    public function testThinsOldRevisionsAndCountsInADryRun(): void
    {
        $em     = SqliteWikiEntityManager::create();
        $space  = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page   = new WikiPage($space, 'old', 'Old');
        $author = new TestUserEntity('ana');
        foreach ([$space, $page, $author] as $entity) {
            $em->persist($entity);
        }
        $at = (new DateTimeImmutable('-10 days'))->setTime(9, 0);
        foreach ([1, 2, 3] as $number) {
            $revision = new WikiPageRevision($page, $number, '<p>x</p>', $author);
            (new ReflectionProperty(WikiPageRevision::class, 'createdAt'))->setValue($revision, $at->modify('+' . $number . ' minutes'));
            $em->persist($revision);
        }
        $em->flush();

        $tester = new CommandTester(new WikiHistoryThinCommand(new WikiHistoryThinner($em, 3)));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('2 revision(s) of 1 page(s) older than 3 days would be thinned (dry run', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('2 revision(s) of 1 page(s) older than 3 days thinned.', $tester->getDisplay());
    }

    public function testWithoutTheTimelineThereIsNothingToThin(): void
    {
        $tester = new CommandTester(new WikiHistoryThinCommand());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('The history timeline is disabled', $tester->getDisplay());
    }
}
