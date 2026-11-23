<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Attachment\WikiAttachmentStorageInterface;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\PageFile\WikiPageFileExtractor;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Repository\WikiCommentRepositoryInterface;
use Nowo\WikiBundle\Routing\WikiTenantPaths;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiInvalidIconException;
use Nowo\WikiBundle\Service\WikiInvalidSlugException;
use Nowo\WikiBundle\Service\WikiPageOperations;
use Nowo\WikiBundle\Service\WikiPageOperationsInterface;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Service\WikiSlugTakenException;
use Nowo\WikiBundle\Service\WikiSpaceOperations;
use Nowo\WikiBundle\Service\WikiSpaceServiceInterface;
use Nowo\WikiBundle\Space\WikiSpaceLifecycle;
use Nowo\WikiBundle\Space\WikiSpaceOwnerMismatchException;
use Nowo\WikiBundle\Space\WikiSpacePurgeInTransactionException;
use Nowo\WikiBundle\Space\WikiSpacePurger;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Util\WikiSlugger;
use Nowo\WikiBundle\Util\WikiSpaceSlugRule;
use Nowo\WikiBundle\ValueObject\WikiCommentSnapshot;
use Nowo\WikiBundle\ValueObject\WikiNewSpace;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;
use function fclose;
use function fgets;
use function in_array;
use function is_resource;
use function is_string;
use function preg_match;
use function proc_close;
use function proc_open;
use function str_repeat;
use function str_starts_with;
use function stream_get_contents;
use function strtoupper;
use function substr;
use function trim;

use const PHP_BINARY;

/**
 * 1.55 (Beacon): the id operations a host needs to stop writing wiki rows with its own entity manager, on SQLite and —
 * through WIKI_TEST_DATABASE_URL — MySQL 8.4, MariaDB 11.4 and PostgreSQL 16:
 *
 * - {@see WikiSpaceOperations::createSpaceFrom()}: the values checked as the space settings are, tenant-path slugs,
 *   exact owner ids (look-alike owners are different owners), the slug race answered by the unique key (a deterministic
 *   interleaving everywhere, and a real second connection that holds the same row on the servers), a lock conflict
 *   that is retry-safe, the insert joining a transaction the host opened, the host's pending changes never flushed;
 * - {@see WikiSpaceLifecycle::eraseSpace()}: forced, complete and idempotent, refused inside a transaction;
 * - the page and comment read models of {@see WikiPageOperations}.
 */
final class WikiBeaconIdOperationsTest extends TestCase
{
    private const BEACON_OWNER = '0b0c6b66-5c43-4c49-9a1c-3f0b8e2a7d11';

    private EntityManager $em;

    private WikiTransactionRunner $runner;

    /** @var array<string, Closure(): void> regex => runs once before the next statement it matches */
    private array $hooks = [];

    /** @var list<string> attachment storage keys deleted */
    private array $deletedKeys = [];

    /** @var list<string> attachment storage keys whose delete fails */
    private array $brokenKeys = [];

    private TestUserEntity $author;

    protected function setUp(): void
    {
        $test     = $this;
        $this->em = WikiTestDatabase::create(new class($test) extends AbstractLogger {
            public function __construct(private readonly WikiBeaconIdOperationsTest $test)
            {
            }

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $sql = $context['sql'] ?? null;
                if (is_string($sql)) {
                    $this->test->onStatement($sql);
                }
            }
        });
        $this->runner = new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0);
        $this->author = new TestUserEntity('u-author');
        $this->em->persist($this->author);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->getConnection()->close();
        }
    }

    /** Called by the SQL log before each statement. */
    public function onStatement(string $sql): void
    {
        foreach ($this->hooks as $pattern => $run) {
            if (preg_match($pattern, $sql) === 1) {
                unset($this->hooks[$pattern]);
                $run();
            }
        }
    }

    /** Called by the test storage. */
    public function deleted(string $key): void
    {
        if (in_array($key, $this->brokenKeys, true)) {
            throw new RuntimeException('disk full');
        }
        $this->deletedKeys[] = $key;
    }

    public function testCreateSpaceFromWritesExactlyTheGivenValuesAndReturnsTheId(): void
    {
        $id = $this->spaces()->createSpaceFrom(new WikiNewSpace('runbooks', '  Runbooks  ', 'team-1', WikiSpaceOwnerScope::Team, 'tabler:book'));

        $row = $this->em->getConnection()->fetchAssociative('SELECT * FROM wiki_spaces WHERE id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame(['runbooks', 'Runbooks', 'team', 'team-1', 'tabler:book', 1], [$row['slug'], $row['name'], $row['owner_scope_type'], $row['owner_scope_id'], $row['icon'], (int) $row['lock_version']]);
        self::assertNull($row['archived_at']);
        self::assertNull($row['purge_started_at']);
        $snapshot = $this->spaces()->findSpace($id);
        self::assertSame('Runbooks', $snapshot?->name);
        self::assertSame('tabler:book', $snapshot->icon);
        self::assertFalse($snapshot->isArchived());

        // A personal (user) space, no icon.
        $user = $this->spaces()->createSpaceFrom(new WikiNewSpace('notes', 'Notes', 'u-ana', WikiSpaceOwnerScope::User));
        self::assertSame(WikiSpaceOwnerScope::User, $this->spaces()->findSpace($user)?->ownerScopeType);
        self::assertNull($this->spaces()->findSpace($user)->icon);
    }

    public function testCreateSpaceGoesThroughTheSameUnit(): void
    {
        $snapshot = $this->spaces()->createSpace('Release Notes', WikiSpaceOwnerScope::Team, 'team-1');

        self::assertSame('release-notes', $snapshot->slug);
        self::assertSame($snapshot->id, (new DoctrineOrmWikiSpaceRepository($this->em))->findBySlug(WikiSpaceOwnerScope::Team, 'team-1', 'release-notes')?->getId());

        // Deleted by another request right after the insert committed: the values written are answered.
        $this->hooks['/^INSERT INTO wiki_spaces/'] = function (): void {
            $this->hooks['/^SELECT /'] = function (): void {
                $this->em->getConnection()->executeStatement("DELETE FROM wiki_spaces WHERE slug = 'gone'");
            };
        };
        $gone = $this->spaces()->createSpace('Gone', WikiSpaceOwnerScope::Team, 'team-1', 'gone');
        self::assertSame('gone', $gone->slug);
        self::assertNull($this->spaces()->findSpace($gone->id));
    }

    public function testInvalidValuesAreRefusedBeforeAnythingIsWritten(): void
    {
        $ops   = $this->spaces();
        $cases = [
            'empty name'       => [new WikiNewSpace('a', '   ', 'team-1'), InvalidArgumentException::class],
            'long name'        => [new WikiNewSpace('a', str_repeat('n', 256), 'team-1'), InvalidArgumentException::class],
            'blank owner'      => [new WikiNewSpace('a', 'A', ''), InvalidArgumentException::class],
            'padded owner'     => [new WikiNewSpace('a', 'A', 'team-1 '), InvalidArgumentException::class],
            'zero-width owner' => [new WikiNewSpace('a', 'A', "team\u{200B}1"), InvalidArgumentException::class],
            'upper slug'       => [new WikiNewSpace('Docs', 'A', 'team-1'), WikiInvalidSlugException::class],
            'double dash slug' => [new WikiNewSpace('a--b', 'A', 'team-1'), WikiInvalidSlugException::class],
            'empty slug'       => [new WikiNewSpace('', 'A', 'team-1'), WikiInvalidSlugException::class],
            'long slug'        => [new WikiNewSpace(str_repeat('a', 121), 'A', 'team-1'), WikiInvalidSlugException::class],
            'icon format'      => [new WikiNewSpace('a', 'A', 'team-1', WikiSpaceOwnerScope::Team, 'book'), WikiInvalidIconException::class],
            'icon prefix'      => [new WikiNewSpace('a', 'A', 'team-1', WikiSpaceOwnerScope::Team, 'evil:book'), WikiInvalidIconException::class],
        ];
        foreach ($cases as $case => [$space, $expected]) {
            try {
                $ops->createSpaceFrom($space);
                self::fail($case);
            } catch (InvalidArgumentException $exception) {
                self::assertInstanceOf($expected, $exception, $case);
                if ($expected === InvalidArgumentException::class) {
                    self::assertNotInstanceOf(WikiInvalidSlugException::class, $exception, $case);
                }
            }
        }
        self::assertSame(0, $this->spaceRows());

        // The longest slug and name the columns hold.
        $ops->createSpaceFrom(new WikiNewSpace(str_repeat('a', 120), str_repeat('n', 255), 'team-1'));
        self::assertSame(1, $this->spaceRows());
    }

    public function testATenantPathSlugIsAcceptedOnlyWhenATenantServesIt(): void
    {
        $slug = self::BEACON_OWNER . '--runbooks';

        try {
            $this->spaces()->createSpaceFrom(new WikiNewSpace($slug, 'Runbooks', self::BEACON_OWNER));
            self::fail('Without tenant paths the bundle rule applies.');
        } catch (WikiInvalidSlugException) {
        }

        $ops = $this->spaces($this->tenantRule());
        try {
            // Lowercase, digits and dashes, but no tenant serves it.
            $ops->createSpaceFrom(new WikiNewSpace('team-1--runbooks', 'Runbooks', 'team-1'));
            self::fail('No tenant matches.');
        } catch (WikiInvalidSlugException) {
        }
        $id = $ops->createSpaceFrom(new WikiNewSpace($slug, 'Runbooks', self::BEACON_OWNER));
        self::assertSame($slug, $ops->findSpace($id)?->slug);
        // The first wiki of the owner: slug = owner id.
        self::assertSame(self::BEACON_OWNER, $ops->findSpace($ops->createSpaceFrom(new WikiNewSpace(self::BEACON_OWNER, 'Wiki', self::BEACON_OWNER)))?->slug);

        // Still only lowercase letters, digits and dashes, never at either end, whatever the tenant pattern allows.
        $loose = $this->spaces(new WikiSpaceSlugRule(new WikiSlugger(), new WikiTenantPaths([['path' => '/w/{spaceSlug}', 'name_prefix' => 'w_', 'slug_requirement' => '[^/]+']], [])));
        foreach (['-x--y', 'x--y-', 'X--y', 'x--y z', 'x/y'] as $bad) {
            try {
                $loose->createSpaceFrom(new WikiNewSpace($bad, 'Bad', 'team-1'));
                self::fail($bad);
            } catch (WikiInvalidSlugException) {
            }
        }
        self::assertSame('x--y', $loose->findSpace($loose->createSpaceFrom(new WikiNewSpace('x--y', 'Ok', 'team-1')))?->slug);
    }

    public function testRenameAcceptsATenantPathSlugWithTheRule(): void
    {
        $space = $this->space('docs', self::BEACON_OWNER);
        $slug  = self::BEACON_OWNER . '--handbook';

        try {
            $this->lifecycle()->renameSpace($space->getId(), null, $slug);
            self::fail('Without the rule the bundle slug rule applies.');
        } catch (WikiInvalidSlugException) {
        }
        self::assertSame($slug, $this->lifecycle($this->tenantRule())->renameSpace($space->getId(), null, $slug)->slug);
    }

    public function testATakenSlugIsRefusedAndLookAlikeOwnersAreDifferentOwners(): void
    {
        $ops = $this->spaces();
        $ops->createSpaceFrom(new WikiNewSpace('docs', 'Docs', 'team-a'));

        try {
            $ops->createSpaceFrom(new WikiNewSpace('docs', 'Docs again', 'team-a'));
            self::fail('Expected the slug to be taken.');
        } catch (WikiSlugTakenException $taken) {
            self::assertNull($taken->getPrevious(), 'refused by the lookup, before any statement failed');
        }

        // House rule LQ: `Team-a` is another owner, and so is the user scope of the same id.
        $ops->createSpaceFrom(new WikiNewSpace('docs', 'Docs', 'Team-a'));
        $ops->createSpaceFrom(new WikiNewSpace('docs', 'Docs', 'team-a', WikiSpaceOwnerScope::User));
        self::assertSame(3, $this->spaceRows());
    }

    public function testASlugTakenBetweenTheLookupAndTheInsertAnswersTheUniqueKey(): void
    {
        // Another request commits the same slug after this one looked it up, before its INSERT.
        $other                                     = WikiTestDatabase::isServer() ? WikiTestDatabase::connect()->getConnection() : $this->em->getConnection();
        $this->hooks['/^INSERT INTO wiki_spaces/'] = static function () use ($other): void {
            $other->insert('wiki_spaces', ['id' => '00000000-0000-4000-8000-0000000000aa', 'created_at' => '2026-11-23 09:00:00', 'updated_at' => '2026-11-23 09:00:00', 'lock_version' => 1, 'slug' => 'docs', 'name' => 'Other', 'owner_scope_type' => 'team', 'owner_scope_id' => 'team-1']);
        };
        $this->em->persist($pending = new TestUserEntity('u-pending'));

        try {
            $this->spaces()->createSpaceFrom(new WikiNewSpace('docs', 'Docs', 'team-1'));
            self::fail('Expected the slug to be taken.');
        } catch (WikiSlugTakenException $taken) {
            self::assertNotNull($taken->getPrevious());
        }

        // On a server the other request's row stays; on SQLite it shared this unit's connection and went with its rollback.
        self::assertSame(WikiTestDatabase::isServer() ? 1 : 0, $this->spaceRows());
        self::assertTrue($this->em->isOpen());
        self::assertFalse($this->em->getConnection()->isTransactionActive());
        // The host's pending change is neither flushed nor forgotten: it is its own to flush.
        self::assertTrue($this->em->getUnitOfWork()->isScheduledForInsert($pending));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM test_users WHERE id = 'u-pending'"));
        $this->em->flush();
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM test_users WHERE id = 'u-pending'"));
    }

    public function testInsideAHostTransactionTheInsertCommitsOrRollsBackWithIt(): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        $rolledBack = $this->spaces()->createSpaceFrom(new WikiNewSpace('draft', 'Draft', 'team-1'));
        self::assertSame(1, $connection->getTransactionNestingLevel(), 'the unit closed its savepoint');
        $connection->rollBack();
        self::assertNull($this->spaces()->findSpace($rolledBack));

        $connection->beginTransaction();
        $kept = $this->spaces()->createSpaceFrom(new WikiNewSpace('kept', 'Kept', 'team-1'));
        // A taken slug inside the host's transaction rolls back to the unit's savepoint only (PostgreSQL included).
        try {
            $this->spaces()->createSpaceFrom(new WikiNewSpace('kept', 'Kept', 'team-1'));
            self::fail('Expected the slug to be taken.');
        } catch (WikiSlugTakenException) {
        }
        $connection->commit();
        self::assertSame('kept', $this->spaces()->findSpace($kept)?->slug);
        self::assertSame(1, $this->spaceRows());
    }

    public function testARealSecondConnectionHoldingTheSameSlugDecidesTheOutcome(): void
    {
        if (!WikiTestDatabase::isServer()) {
            self::markTestSkipped('Needs MySQL, MariaDB or PostgreSQL in ' . WikiTestDatabase::ENV . '.');
        }

        // It commits: this insert waited on the unique key, then is refused (one row, no duplicate, no 500).
        $worker = $this->worker('team-1', 'docs', 1.5, 'commit');
        try {
            $this->spaces()->createSpaceFrom(new WikiNewSpace('docs', 'Docs', 'team-1'));
            self::fail('Expected the slug to be taken.');
        } catch (WikiSlugTakenException $taken) {
            self::assertNotNull($taken->getPrevious());
        }
        self::assertSame('committed', $this->finish($worker));
        self::assertSame(1, $this->spaceRows());

        // It rolls back: this insert goes through once the other row is gone.
        $worker = $this->worker('team-1', 'wiki', 1.5, 'rollback');
        $id     = $this->spaces()->createSpaceFrom(new WikiNewSpace('wiki', 'Wiki', 'team-1'));
        self::assertSame('rolled back', $this->finish($worker));
        self::assertSame('Wiki', $this->spaces()->findSpace($id)?->name);

        // It holds the row past this session's lock timeout: a retry-safe conflict (CONFLICT_001), nothing written.
        $worker = $this->worker('team-1', 'slow', 3, 'rollback');
        $this->shortLockTimeout($this->em->getConnection());
        try {
            $this->spaces()->createSpaceFrom(new WikiNewSpace('slow', 'Slow', 'team-1'));
            self::fail('Expected a lock conflict.');
        } catch (TransientStorageFailure $failure) {
            self::assertNotNull($failure->retryAfterSeconds());
        }
        self::assertSame('rolled back', $this->finish($worker));
        self::assertSame(2, $this->spaceRows());
        self::assertTrue($this->em->isOpen());
    }

    public function testEraseSpaceIsForcedCompleteAndIdempotent(): void
    {
        $space = $this->space('doomed', 'team-1');
        $keep  = $this->space('keep', 'team-1');
        $this->content($space);
        $this->content($keep);
        $lifecycle = $this->lifecycle();

        // Refused inside a transaction (each batch commits on its own): nothing written.
        $this->em->getConnection()->beginTransaction();
        try {
            $lifecycle->eraseSpace($space->getId());
            self::fail('Expected the purge to refuse an open transaction.');
        } catch (WikiSpacePurgeInTransactionException) {
        } finally {
            $this->em->getConnection()->rollBack();
        }
        self::assertSame(2, $this->spaceRows());

        // An active space (no archive first): archived, then purged in batches of one page.
        $result = $lifecycle->eraseSpace($space->getId());
        self::assertTrue($result->finished);
        self::assertSame(2, $result->pages);
        self::assertSame(3, $result->batches);
        self::assertSame(['doomed-a'], $this->deletedKeys);
        foreach (['wiki_spaces' => 1, 'wiki_pages' => 2, 'wiki_page_revisions' => 2, 'wiki_comment_threads' => 1, 'wiki_comments' => 2, 'wiki_attachments' => 1] as $table => $left) {
            self::assertSame($left, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table), $table);
        }
        self::assertNotNull($this->spaces()->findSpace($keep->getId()));

        // Again (a retried erasure, a replayed message), and for an id that never existed: done, nothing to catch.
        foreach ([$space->getId(), '00000000-0000-4000-8000-000000000000'] as $id) {
            $again = $lifecycle->eraseSpace($id);
            self::assertTrue($again->finished);
            self::assertSame([$id, 0, 0], [$again->spaceId, $again->pages, $again->batches]);
            self::assertFalse($again->found);
            self::assertTrue($again->alreadyGone());
        }
        self::assertTrue($result->found);
        self::assertFalse($result->alreadyGone());
        self::assertFalse($result->needsFileSweep());

        // A budget: one batch per call, resumed by the next call.
        $first = $lifecycle->eraseSpace($keep->getId(), 1);
        self::assertFalse($first->finished);
        self::assertTrue($this->spaces()->findSpace($keep->getId())?->isArchived());
        self::assertTrue($lifecycle->eraseSpace($keep->getId())->finished);
        self::assertSame(0, $this->spaceRows());
    }

    public function testEraseSpaceRefusesALookAlikeIdInsteadOfAnsweringAlreadyGone(): void
    {
        $space = $this->space('doomed', 'team-1');
        $this->content($space);
        $id = $space->getId();

        // Audit ZAP-1: on a case-insensitive collation the upper-cased id finds the row, the exact compare does not.
        foreach ([strtoupper($id), ' ' . $id, $id . ' ', substr($id, 0, 35), 'doomed', ''] as $lookAlike) {
            try {
                $this->lifecycle()->eraseSpace($lookAlike);
                self::fail('Expected a refusal for "' . $lookAlike . '".');
            } catch (InvalidArgumentException $refused) {
                self::assertStringContainsString('lowercase UUID', $refused->getMessage());
            }
        }
        self::assertSame(1, $this->spaceRows());
        self::assertFalse($this->spaces()->findSpace($id)?->isArchived());
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_pages'));
    }

    public function testEraseSpaceWithAnExpectedOwnerRefusesAnotherOwnerWithoutWriting(): void
    {
        $space = $this->space('doomed', 'team-1');
        $this->content($space);
        $id = $space->getId();

        foreach ([[WikiSpaceOwnerScope::Team, 'team-2'], [WikiSpaceOwnerScope::Team, 'Team-1'], [WikiSpaceOwnerScope::Team, 'team-1 '], [WikiSpaceOwnerScope::User, 'team-1']] as [$type, $owner]) {
            try {
                $this->lifecycle()->eraseSpace($id, null, $type, $owner);
                self::fail('Expected an owner mismatch for ' . $owner);
            } catch (WikiSpaceOwnerMismatchException $mismatch) {
                self::assertSame('The space has another owner.', $mismatch->getMessage());
            }
        }
        foreach ([[WikiSpaceOwnerScope::Team, null], [null, 'team-1']] as [$type, $owner]) {
            try {
                $this->lifecycle()->eraseSpace($id, null, $type, $owner);
                self::fail('Expected both owner values to be required.');
            } catch (InvalidArgumentException) {
            }
        }
        // Nothing written: not archived, not marked, every page there.
        $row = $this->em->getConnection()->fetchAssociative('SELECT archived_at, purge_started_at, lock_version FROM wiki_spaces WHERE id = ?', [$id]);
        self::assertIsArray($row);
        self::assertSame([null, null, 1], [$row['archived_at'], $row['purge_started_at'], (int) $row['lock_version']]);
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_pages'));

        // The right owner: erased. Then gone: the expected owner cannot be checked any more, the answer is "already gone".
        $result = $this->lifecycle()->eraseSpace($id, null, WikiSpaceOwnerScope::Team, 'team-1');
        self::assertTrue($result->finished);
        self::assertTrue($result->found);
        self::assertTrue($this->lifecycle()->eraseSpace($id, null, WikiSpaceOwnerScope::Team, 'team-1')->alreadyGone());
    }

    public function testEraseSpaceCountsFilesThatMayStillBeStored(): void
    {
        $space = $this->space('doomed', 'team-1');
        $this->content($space);
        $this->brokenKeys = ['doomed-a'];
        // Every revision refers to two files; one file listed per batch: the event list is cut.
        foreach ($this->em->getRepository(WikiPageRevision::class)->findAll() as $revision) {
            $this->em->getConnection()->executeStatement('UPDATE wiki_page_revisions SET content_markdown = ? WHERE id = ?', ['![a](/files/a.png) ![b](/files/b.png)', $revision->getId()]);
        }
        $this->em->clear();

        $result = $this->lifecycle(null, 1)->eraseSpace($space->getId());

        self::assertTrue($result->finished);
        self::assertSame(1, $result->filesFailed);
        self::assertSame(2, $result->filesTruncated);
        self::assertTrue($result->needsFileSweep());
        self::assertSame(0, $this->spaceRows());
    }

    public function testThreadCommentsAreCapped(): void
    {
        $space              = $this->space('docs', 'team-1');
        [, $thread, $first] = $this->content($space);
        $repository         = new DoctrineOrmWikiCommentRepository($this->em);

        self::assertSame([$first->getId()], array_map(static fn (WikiComment $c): string => $c->getId(), $repository->findThreadComments($thread->getId(), 1)));
        self::assertCount(2, $repository->findThreadComments($thread->getId(), WikiPageOperationsInterface::MAX_THREAD_COMMENTS));
        self::assertSame([], $repository->findThreadComments(strtoupper($thread->getId()), 10));
    }

    public function testPageAndCommentReadModelsById(): void
    {
        $space                           = $this->space('docs', 'team-1');
        [$page, $thread, $first, $reply] = $this->content($space);
        $reply->markDeleted();
        $thread->resolve($this->author);
        $this->em->flush();
        $ops = $this->pages(new DoctrineOrmWikiCommentRepository($this->em));

        $revision = $ops->findPageRevision($page->getId(), 1);
        self::assertSame([$page->getId(), 1, 'u-author'], [$revision?->pageId, $revision?->revisionNumber, $revision?->authorId]);
        self::assertNull($ops->findPageRevision($page->getId(), 2));
        self::assertNull($ops->findPageRevision(strtoupper($page->getId()), 1));
        self::assertNull($ops->findPageRevision('missing', 1));

        $snapshot = $ops->findCommentThread($thread->getId());
        self::assertSame([$thread->getId(), $page->getId(), 'u-author', 'u-author', true], [$snapshot?->id, $snapshot?->pageId, $snapshot?->createdById, $snapshot?->resolvedById, $snapshot?->pageLevel]);
        self::assertTrue($snapshot->isResolved());
        self::assertNull($ops->findCommentThread(strtoupper($thread->getId())));
        self::assertNull($ops->findCommentThread('missing'));

        $comment = $ops->findComment($first->getId());
        self::assertSame([$first->getId(), $thread->getId(), $page->getId(), 'u-author', 0], [$comment?->id, $comment?->threadId, $comment?->pageId, $comment?->authorId, $comment?->position]);
        self::assertFalse($comment->isDeleted());
        self::assertNull($comment->editedAt);
        self::assertTrue($ops->findComment($reply->getId())?->isDeleted());
        self::assertNull($ops->findComment(strtoupper($first->getId())));
        self::assertNull($ops->findComment('missing'));

        self::assertSame([$first->getId(), $reply->getId()], array_map(static fn (WikiCommentSnapshot $c): string => $c->id, $ops->findThreadComments($thread->getId())));
        self::assertSame([], $ops->findThreadComments(strtoupper($thread->getId())));
    }

    public function testCommentReadsWithoutCommentsOrWithoutAFinderAnswerNothing(): void
    {
        $space              = $this->space('docs', 'team-1');
        [, $thread, $first] = $this->content($space);

        foreach ([null, $this->createStub(WikiCommentRepositoryInterface::class)] as $comments) {
            $ops = $this->pages($comments);
            self::assertNull($ops->findCommentThread($thread->getId()));
            self::assertNull($ops->findComment($first->getId()));
            self::assertSame([], $ops->findThreadComments($thread->getId()));
        }
    }

    private function spaces(WikiSpaceSlugRule $rule = new WikiSpaceSlugRule()): WikiSpaceOperations
    {
        $spaces  = new DoctrineOrmWikiSpaceRepository($this->em);
        $service = $this->createStub(WikiSpaceServiceInterface::class);

        return new WikiSpaceOperations($service, $spaces, $this->runner, $rule);
    }

    private function pages(?WikiCommentRepositoryInterface $comments): WikiPageOperations
    {
        return new WikiPageOperations($this->createStub(WikiPageServiceInterface::class), new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiSpaceRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), null, $comments);
    }

    private function tenantRule(): WikiSpaceSlugRule
    {
        $uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

        return new WikiSpaceSlugRule(new WikiSlugger(), new WikiTenantPaths([
            ['path' => '/organizations/{spaceSlug}/wiki', 'name_prefix' => 'org_', 'slug_requirement' => 'org-' . $uuid . '(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?'],
            ['path' => '/projects/{spaceSlug}/wiki', 'name_prefix' => 'project_', 'slug_requirement' => $uuid . '(?:--[a-z0-9]+(?:-[a-z0-9]+)*)?'],
        ], []));
    }

    private function lifecycle(?WikiSpaceSlugRule $rule = null, ?int $maxFiles = null): WikiSpaceLifecycle
    {
        $test    = $this;
        $storage = new class($test) implements WikiAttachmentStorageInterface {
            public function __construct(private readonly WikiBeaconIdOperationsTest $test)
            {
            }

            public function write(string $key, mixed $stream): void
            {
            }

            public function readStream(string $key): mixed
            {
                return null;
            }

            public function delete(string $key): void
            {
                $this->test->deleted($key);
            }

            public function keys(): iterable
            {
                return [];
            }
        };
        $checker = new class implements WikiSpaceAccessCheckerInterface {
            public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
            {
                return false;
            }
        };
        $tables = ['comments' => true, 'trash' => true, 'history_timeline' => true, 'collaboration' => true, 'favourites' => true, 'recents' => true, 'backlinks' => true, 'api_idempotency' => true];
        $purger = $maxFiles === null
            ? new WikiSpacePurger($this->runner, $tables)
            : new WikiSpacePurger($this->runner, $tables, new WikiPageFileExtractor(), new DoctrineOrmWikiPageRevisionRepository($this->em), $maxFiles);

        return new WikiSpaceLifecycle($this->runner, new DoctrineOrmWikiSpaceRepository($this->em), $purger, new WikiSlugger(), $checker, new EventDispatcher(), null, $storage, batchSize: 1, slugRule: $rule);
    }

    private function space(string $slug, string $owner): WikiSpace
    {
        $space = new WikiSpace($slug, $slug, WikiSpaceOwnerScope::Team, $owner);
        $this->em->persist($space);
        $this->em->flush();

        return $space;
    }

    /**
     * Two pages with a revision each; on the first, a thread with two comments and an attachment.
     *
     * @return array{WikiPage, WikiCommentThread, WikiComment, WikiComment}
     */
    private function content(WikiSpace $space): array
    {
        $page  = new WikiPage($space, 'intro', 'Intro');
        $other = new WikiPage($space, 'other', 'Other');
        foreach ([$page, $other] as $each) {
            $revision = new WikiPageRevision($each, 1, '<p>x</p>', $this->author);
            $each->setCurrentRevision($revision);
            $this->em->persist($each);
            $this->em->persist($revision);
        }
        $thread = new WikiCommentThread($page, $this->author);
        $first  = new WikiComment($thread, $this->author, 'Hello');
        $reply  = new WikiComment($thread, $this->author, 'Reply', 1);
        foreach ([$thread, $first, $reply] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->persist(new WikiAttachment($page, $space, 'a.txt', $space->getSlug() . '-a', 'text/plain', 1, str_repeat('0', 64), $this->author));
        $this->em->flush();

        return [$page, $thread, $first, $reply];
    }

    private function spaceRows(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_spaces');
    }

    private function shortLockTimeout(Connection $connection): void
    {
        $connection->executeStatement(str_starts_with((string) WikiTestDatabase::url(), 'mysql')
            ? 'SET SESSION innodb_lock_wait_timeout = 1'
            : "SET lock_timeout = '1s'");
    }

    /**
     * Starts the worker and waits until its row is written (uncommitted).
     *
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function worker(string $owner, string $slug, float $hold, string $end): array
    {
        $process = proc_open([PHP_BINARY, __DIR__ . '/Race/space-worker.php', $owner, $slug, (string) $hold, $end], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertTrue(is_resource($process));
        $line = trim((string) fgets($pipes[1]));
        // stderr is read only on failure: reading it first would wait for the worker to exit (its commit).
        if ($line !== 'ready') {
            self::fail('The worker did not start: ' . stream_get_contents($pipes[2]));
        }
        // This session waits up to 5 s for the worker's end (it holds its row 1.5 s).
        $this->em->getConnection()->executeStatement(str_starts_with((string) WikiTestDatabase::url(), 'mysql')
            ? 'SET SESSION innodb_lock_wait_timeout = 5'
            : "SET lock_timeout = '5s'");

        return [$process, $pipes];
    }

    /**
     * @param array{0: resource, 1: array<int, resource>} $worker
     */
    private function finish(array $worker): string
    {
        [$process, $pipes] = $worker;
        $line              = trim((string) fgets($pipes[1]));
        $errors            = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        self::assertSame('', $errors);

        return $line;
    }
}
