<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Nowo\WikiBundle\Command\WikiTrashPurgeCommand;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiTrashRepository;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Trash\WikiTrashService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * 1.14 (T065): wiki:trash:purge removes the deletions older than the retention.
 */
final class WikiTrashPurgeCommandTest extends TestCase
{
    public function testPurgesExpiredDeletionsAndCountsInADryRun(): void
    {
        $em    = SqliteWikiEntityManager::create();
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'old', 'Old');
        $em->persist($space);
        $em->persist($page);
        $em->flush();
        $pages = new DoctrineOrmWikiPageRepository($em);
        $trash = new WikiTrashService($pages, new DoctrineOrmWikiTrashRepository($em), $pages, new EventDispatcher(), retentionDays: 3);
        $trash->delete($page);
        $em->createQueryBuilder()->update(WikiTrashEntry::class, 't')->set('t.deletedAt', ':at')
            ->setParameter('at', new DateTimeImmutable('-5 days'))->getQuery()->execute();

        $tester = new CommandTester(new WikiTrashPurgeCommand($trash));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('1 deletion(s) with 1 page(s) are older than 3 days (dry run', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('1 deletion(s) with 1 page(s) older than 3 days purged.', $tester->getDisplay());
    }

    public function testWithoutTheTrashThereIsNothingToPurge(): void
    {
        $tester = new CommandTester(new WikiTrashPurgeCommand());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('The trash is disabled', $tester->getDisplay());
    }
}
