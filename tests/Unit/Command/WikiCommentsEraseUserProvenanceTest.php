<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManager;
use Nowo\WikiBundle\Command\WikiCommentsEraseUserCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Provenance\WikiProvenanceAuthorErasedListener;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
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
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_column;
use function array_keys;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Spec 234, audit PQ-L1: the bundle's user-erasure path (wiki:comments:erase-user, WikiCommentService::eraseAuthor())
 * exports and erases the actor labels of the user's revisions, and those whose author was already deleted.
 */
final class WikiCommentsEraseUserProvenanceTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private WikiPage $page;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->page  = new WikiPage($this->space, 'run', 'Run');
        $ana         = new TestUserEntity('ana');
        $bob         = new TestUserEntity('bob');
        $gone        = new TestUserEntity('gone');
        $thread      = new WikiCommentThread($this->page, $ana);
        foreach ([$this->space, $this->page, $ana, $bob, $gone, $thread, new WikiComment($thread, $ana, 'Mine')] as $entity) {
            $this->em->persist($entity);
        }
        foreach ([[1, $ana, 'Ana CI token'], [2, $bob, 'Bob token'], [3, $gone, 'Gone token']] as [$number, $author, $label]) {
            $revision = new WikiPageRevision($this->page, $number, '<p>v</p>', $author);
            $revision->recordProvenance(new WikiWriteProvenance('api', $label));
            $this->em->persist($revision);
        }
        $this->em->flush();
        // Gone's account was deleted by the host (ON DELETE SET NULL); its label is still in the row.
        $this->em->getConnection()->executeStatement("UPDATE wiki_page_revisions SET author_id = NULL WHERE author_id = 'gone'");
    }

    public function testTheCommandExportsAndErasesTheActorLabels(): void
    {
        $erasure = new WikiWriteProvenanceErasure($this->em);
        $tester  = new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($this->em, TestUserEntity::class), $this->comments(), $erasure));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--export' => true]));
        $export = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['Mine'], array_column($export['comments'], 'body'));
        self::assertSame(['Ana CI token'], array_column($export['revision_actor_labels'], 'actor_label'));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'ana', '--force' => true]));
        self::assertStringContainsString('1 revision actor label(s) erased (1 of deleted authors)', $tester->getDisplay());
        self::assertSame([null, 'Bob token', null], $this->labels());
    }

    public function testWithCommentsOffTheCommandStillErasesTheActorLabels(): void
    {
        $tester = new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($this->em, TestUserEntity::class), null, new WikiWriteProvenanceErasure($this->em)));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'bob', '--export' => true]));
        self::assertSame(['revision_actor_labels'], array_keys(json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR)));

        $tester->setInputs(['no']);
        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'bob'], ['interactive' => true]));
        self::assertStringContainsString('Erase the 1 revision actor label(s) of "bob"?', $tester->getDisplay());
        self::assertSame(['Ana CI token', 'Bob token', 'Gone token'], $this->labels());

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => 'bob', '--force' => true]));
        self::assertStringContainsString('1 revision actor label(s) erased (1 of deleted authors)', $tester->getDisplay());
        self::assertSame(['Ana CI token', null, null], $this->labels());
    }

    /** A host that calls WikiCommentService::eraseAuthor() itself gets the labels erased through the event. */
    public function testTheCommentAuthorErasureErasesTheActorLabels(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(WikiEvents::COMMENT_AUTHOR_ERASED, new WikiProvenanceAuthorErasedListener(new WikiWriteProvenanceErasure($this->em)));
        $ana = $this->em->find(TestUserEntity::class, 'ana');
        self::assertInstanceOf(TestUserEntity::class, $ana);

        $this->comments($dispatcher)->eraseAuthor($ana);
        self::assertSame([null, 'Bob token', null], $this->labels());
    }

    private function comments(?EventDispatcher $dispatcher = null): WikiCommentService
    {
        return new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), new WikiSpaceGuard(new FixedSpaceLocator($this->space), CallbackSpaceAccessChecker::allowing()), new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), dispatcher: $dispatcher);
    }

    /**
     * @return list<string|null>
     */
    private function labels(): array
    {
        /* @var list<string|null> */
        return $this->em->getConnection()->fetchFirstColumn('SELECT actor_label FROM wiki_page_revisions ORDER BY revision_number');
    }
}
