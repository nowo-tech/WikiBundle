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

    public function testErasingAUserAlsoErasesTheirMentions(): void
    {
        // Audit FG-L2: the CLI erasure covers the user's @mentions in other people's comments too.
        $em     = SqliteWikiEntityManager::create();
        $space  = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page   = new WikiPage($space, 'run', 'Run');
        $ana    = (new TestUserEntity('ana'))->withHandle('@ana_g');
        $bob    = new TestUserEntity('bob');
        $thread = new WikiCommentThread($page, $bob);
        $byBob  = new WikiComment($thread, $bob, 'Ask @ana_g or @AnaG, not @ana_gx.');
        foreach ([$space, $page, $ana, $bob, $thread, $byBob, new WikiComment($thread, $ana, 'Mine', 1)] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $service = new WikiCommentService(new DoctrineOrmWikiCommentRepository($em), new WikiSpaceGuard(new FixedSpaceLocator($space), CallbackSpaceAccessChecker::allowing()), new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer());
        $tester  = new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($em, TestUserEntity::class), $service));
        $body    = static fn (): mixed => $em->getConnection()->fetchOne('SELECT body FROM wiki_comments WHERE id = ?', [$byBob->getId()]);

        // Refused before anything changes: a label that could render as a mention, a value that is not a handle.
        self::assertSame(Command::FAILURE, $tester->execute(['user' => 'ana', '--force' => true, '--mention-label' => '@deleted-user']));
        self::assertStringContainsString('could render as a mention', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['user' => 'ana', '--force' => true, '--handle' => ['not a handle']]));
        self::assertStringContainsString('"not a handle" is not a mention handle', $tester->getDisplay());
        self::assertSame('Ask @ana_g or @AnaG, not @ana_gx.', $body());

        // The question names the handles; getHandle() and --handle are both used.
        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--handle' => ['@AnaG', 'ana_g']], ['interactive' => true]));
        self::assertStringContainsString('and their mentions (@AnaG, @ana_g)?', $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true, '--handle' => ['@AnaG']]));
        self::assertStringContainsString('1 comment(s) or thread(s) with mentions of @AnaG, @ana_g rewritten.', $tester->getDisplay());
        self::assertSame('Ask @… or @…, not @ana_gx.', $body());

        // Without any handle the erasure warns that mentions were kept.
        $ana->withHandle(null);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('Mentions of the user in other people', $tester->getDisplay());
    }

    public function testWithoutCommentsThereIsNothingToDo(): void
    {
        $tester = new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver(SqliteWikiEntityManager::create(), TestUserEntity::class)));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana']));
        self::assertStringContainsString('Comments are disabled', $tester->getDisplay());
    }
}
