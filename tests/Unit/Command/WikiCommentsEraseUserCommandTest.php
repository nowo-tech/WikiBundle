<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use Nowo\WikiBundle\Command\WikiCommentsEraseUserCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * 1.16 (audit EI-L6): wiki:comments:erase-user exports or erases a user's comments.
 */
final class WikiCommentsEraseUserCommandTest extends TestCase
{
    public function testExportsThenErasesAUsersComments(): void
    {
        $em     = SqliteWikiEntityManager::create();
        $space  = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page   = new WikiPage($space, 'run', 'Run');
        $ana    = new TestUserEntity('ana');
        $thread = new WikiCommentThread($page, $ana);
        foreach ([$space, $page, $ana, $thread, new WikiComment($thread, $ana, 'Mine'), new WikiComment($thread, $ana, 'Also mine', 1)] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $service = new WikiCommentService(new DoctrineOrmWikiCommentRepository($em), new WikiSpaceGuard(new FixedSpaceLocator($space), CallbackSpaceAccessChecker::allowing()), new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer());
        $tester  = new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($em, TestUserEntity::class), $service));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        $export = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['Mine', 'Also mine'], array_column($export['comments'], 'body'));
        self::assertSame([$thread->getId()], array_column($export['threads_started'], 'id'));

        // 1.16.1: interactive runs ask first (default no); --force or --no-interaction skip the question.
        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana'], ['interactive' => true]));
        self::assertStringContainsString('Nothing was changed.', $tester->getDisplay());
        // Audit EI2-I4: the question names the resolved account and what will change.
        self::assertStringContainsString('Erase the 2 wiki comment(s) of "ana"?', $tester->getDisplay());
        $tester->setInputs(['yes']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--keep-bodies' => true], ['interactive' => true]));
        self::assertStringContainsString('2 comment(s) unlinked', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true], ['interactive' => true]));
        self::assertStringContainsString('0 comment(s) erased', $tester->getDisplay());

        self::assertSame(Command::FAILURE, $tester->execute(['user' => 'nobody']));
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testWithoutCommentsThereIsNothingToDo(): void
    {
        $tester = new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver(SqliteWikiEntityManager::create(), TestUserEntity::class)));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana']));
        self::assertStringContainsString('Comments are disabled', $tester->getDisplay());
    }
}
