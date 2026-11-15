<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Ddd;

use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Command\WikiCommentsEraseUserCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Stub\WikiSecurityUserDto;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\User\DoctrineWikiUserReferenceResolver;
use Nowo\WikiBundle\User\WikiUserClassPolicy;
use Nowo\WikiBundle\User\WikiUserDirectoryInterface;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_column;
use function json_decode;
use function preg_replace;

use const JSON_THROW_ON_ERROR;

/**
 * Audit OX-L2 (1.36.0, `database.user_reference: id`): `wiki:comments:erase-user <id>` works when the host's
 * `user_directory` returns a read model (any object with `getId()`, as documented) instead of `user_class`: the
 * command acts on the stored id itself. And a user the wiki cannot reference fails the command before anything is
 * written (never a partial erasure under `--force --handle`).
 */
final class WikiEraseUserIdModeTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    /**
     * Ana (42) wrote a comment and a revision; Bob mentions her; the look-alike 420 wrote a revision.
     */
    private function seed(string $mode): void
    {
        $this->em    = WikiTestDatabase::create(null, $mode);
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page        = new WikiPage($this->space, 'run', 'Run');
        $entities    = [$this->space, $page];
        if ($mode === WikiMetadataListener::USER_REFERENCE_ID) {
            [$ana, $bob, $other] = [new WikiStoredUserRef('42'), new WikiStoredUserRef('7'), new WikiStoredUserRef('420')];
        } else {
            [$ana, $bob, $other] = [new TestUserEntity('42'), new TestUserEntity('7'), new TestUserEntity('420')];
            $entities            = [...$entities, $ana, $bob, $other];
        }
        $thread = new WikiCommentThread($page, $ana);
        foreach ([...$entities, $thread, new WikiComment($thread, $ana, 'Mine'), new WikiComment($thread, $bob, 'Hi @ana')] as $entity) {
            $this->em->persist($entity);
        }
        foreach ([[1, $ana, 'Ana token'], [2, $other, 'Look-alike token']] as [$number, $author, $label]) {
            $revision = new WikiPageRevision($page, $number, '<p>v</p>', $author);
            $revision->recordProvenance(new WikiWriteProvenance('api', $label));
            $this->em->persist($revision);
        }
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    public function testADirectoryReadModelDoesNotStopTheErasureOfTheStoredId(): void
    {
        $this->seed(WikiMetadataListener::USER_REFERENCE_ID);
        $directory = new class implements WikiUserDirectoryInterface {
            public function find(string $id): object
            {
                return new class($id) {
                    public function __construct(private readonly string $id)
                    {
                    }

                    public function getId(): string
                    {
                        return $this->id;
                    }
                };
            }
        };
        $tester = $this->tester(new WikiUsers('id', $directory), WikiSecurityUserDto::class);

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => '42', '--export' => true]));
        $export = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['Mine'], array_column($export['comments'], 'body'));
        self::assertSame(['Ana token'], array_column($export['revision_actor_labels'], 'actor_label'));

        self::assertSame(Command::SUCCESS, $tester->execute(['user' => '42', '--force' => true, '--handle' => ['ana']]));
        self::assertSame(['', 'Hi @…'], $this->bodies(), 'comment erased and the mention rewritten');
        self::assertSame([null, 'Look-alike token'], $this->labels(), 'the exact id only');
    }

    /**
     * A user the comment service cannot reference (here: a `user_class` that is not the resolved user's class, so the
     * reference resolver refuses it) stops the command before the mentions, comments or labels are touched.
     */
    public function testAUserTheWikiCannotReferenceChangesNothing(): void
    {
        $this->seed(WikiMetadataListener::USER_REFERENCE_ENTITY);
        $tester = $this->tester(null, WikiSecurityUserDto::class, WikiMetadataListener::USER_REFERENCE_ENTITY, TestUserEntity::class);

        self::assertSame(Command::FAILURE, $tester->execute(['user' => '42', '--force' => true, '--handle' => ['ana']]));
        self::assertStringContainsString('Nothing was changed', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
        self::assertSame(['Mine', 'Hi @ana'], $this->bodies());
        self::assertSame(['Ana token', 'Look-alike token'], $this->labels());

        self::assertSame(Command::FAILURE, $tester->execute(['user' => '42', '--export' => true]));
        self::assertStringContainsString('Nothing was changed', (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
    }

    /**
     * @param string $userClass the class the wiki's reference resolver accepts
     * @param string $resolveClass the class the CLI resolves users of (entity mode)
     */
    private function tester(?WikiUsers $users, string $userClass, string $mode = WikiMetadataListener::USER_REFERENCE_ID, ?string $resolveClass = null): CommandTester
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);
        $references = new DoctrineWikiUserReferenceResolver($userClass, new WikiEntityManagerProvider($registry), new WikiUserClassPolicy($userClass), $mode);
        $erasure    = new WikiWriteProvenanceErasure($this->em, $references);
        $comments   = new WikiCommentService(new DoctrineOrmWikiCommentRepository($this->em), new WikiSpaceGuard(new FixedSpaceLocator($this->space), CallbackSpaceAccessChecker::allowing()), new WikiAuthorLabel('s'), new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), users: $references, provenance: $erasure);

        return new CommandTester(new WikiCommentsEraseUserCommand(new WikiAuthorResolver($this->em, $resolveClass ?? $userClass, $users), $comments, $erasure));
    }

    /**
     * @return list<string>
     */
    private function bodies(): array
    {
        /* @var list<string> */
        return $this->em->getConnection()->fetchFirstColumn('SELECT body FROM wiki_comments ORDER BY position');
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
