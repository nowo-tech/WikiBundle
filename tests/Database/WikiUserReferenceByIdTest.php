<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Doctrine\Type\WikiUserRefType;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Doctrine\WikiSpaceCollationListener;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiComment;
use Nowo\WikiBundle\Entity\WikiCommentReaction;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageLink;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageMetadataQuery;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;
use function array_values;
use function sort;

/**
 * 1.34.0 (`database.user_reference: id`, REQ-ARCH-001 rule 2 and REQ-SEC-006): the seven user fields are string id
 * columns read back as {@see WikiStoredUserRef}; every lookup and erasure keyed by a user id keeps only the rows whose
 * id is equal byte for byte, so `Bob`, `bób` or the NFD form of `bób` never reach the rows of `bob`.
 *
 * Runs on SQLite in memory and, in CI, on MySQL / MariaDB / PostgreSQL ({@see WikiTestDatabase::ENV}). On MySQL /
 * MariaDB it also runs after putting the id columns on a folding PAD SPACE collation (a host that has not run the
 * UPGRADING `ALTER TABLE`): the PHP re-check alone must hold there.
 */
final class WikiUserReferenceByIdTest extends TestCase
{
    /** Columns of the id mode: table => column. */
    private const COLUMNS = [
        ['wiki_page_revisions', 'author_id', true],
        ['wiki_page_links', 'created_by_id', true],
        ['wiki_attachments', 'uploaded_by_id', true],
        ['wiki_comment_threads', 'created_by_id', true],
        ['wiki_comment_threads', 'resolved_by_id', true],
        ['wiki_comments', 'author_id', true],
        ['wiki_comment_reactions', 'user_id', false],
    ];

    /** Valid ids that fold onto `bob` on a case- or accent-insensitive collation. */
    private const LOOKALIKES = ['Bob', 'BOB', "b\u{f3}b", "bo\u{301}b"];

    private EntityManager $em;

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /** @return iterable<string, array{string|null}> */
    public static function columnCollations(): iterable
    {
        yield 'generated schema' => [null];
        yield 'host not migrated (utf8mb4_unicode_ci)' => ['utf8mb4_unicode_ci'];
    }

    public function testTheUserFieldsAreIdColumnsWithoutForeignKey(): void
    {
        $this->setUpSchema(null);
        $schema = $this->em->getConnection()->createSchemaManager();
        foreach (self::COLUMNS as [$table, $column, $nullable]) {
            $columns = $schema->listTableColumns($table);
            self::assertArrayHasKey($column, $columns, $table . '.' . $column);
            self::assertSame(128, $columns[$column]->getLength(), $table . '.' . $column);
            self::assertSame(!$nullable, $columns[$column]->getNotnull(), $table . '.' . $column);
            foreach ($schema->listTableForeignKeys($table) as $key) {
                self::assertNotContains($column, $key->getLocalColumns(), 'no foreign key on ' . $table . '.' . $column);
            }
            $indexed = false;
            foreach ($schema->listTableIndexes($table) as $index) {
                $indexed = $indexed || $index->getColumns() === [$column];
            }
            self::assertTrue($indexed, 'index on ' . $table . '.' . $column);
            $metadata = $this->em->getClassMetadata($this->classOf($table));
            $field    = $metadata->getFieldForColumn($column);
            self::assertSame(WikiUserRefType::NAME, $metadata->getTypeOfField($field));
        }

        $platform = $this->em->getConnection()->getDatabasePlatform();
        if ($platform instanceof AbstractMySQLPlatform) {
            foreach (self::COLUMNS as [$table, $column]) {
                $collation = $this->em->getConnection()->fetchOne('SELECT COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
                self::assertSame(WikiSpaceCollationListener::collationFor($platform), $collation, $table . '.' . $column);
            }
        }
    }

    public function testRowsKeepTheExactIdAndOutliveTheirUser(): void
    {
        $this->setUpSchema(null);
        [$page, $revision] = $this->page(new TestUser('alice'));
        $link              = new WikiPageLink($page, 'ticket', 'T-1', new WikiStoredUserRef('bob'));
        $attachment        = new WikiAttachment($page, $page->getSpace(), 'a.txt', 'k/a.txt', 'text/plain', 1, 'h', new WikiStoredUserRef('Bób'));
        $this->em->persist($link);
        $this->em->persist($attachment);
        $this->em->flush();
        $spaceId = $page->getSpace()->getId();
        $this->em->clear();

        $revision = $this->em->find(WikiPageRevision::class, $revision->getId());
        self::assertInstanceOf(WikiPageRevision::class, $revision);
        self::assertEquals(new WikiStoredUserRef('alice'), $revision->getAuthor());
        $link = $this->em->find(WikiPageLink::class, $link->getId());
        self::assertEquals(new WikiStoredUserRef('bob'), $link?->getCreatedBy());
        $attachment = $this->em->find(WikiAttachment::class, $attachment->getId());
        self::assertEquals(new WikiStoredUserRef('Bób'), $attachment?->getUploadedBy());

        $space = $this->em->find(WikiSpace::class, $spaceId);
        self::assertInstanceOf(WikiSpace::class, $space);
        $metadata = (new DoctrineOrmWikiPageRepository($this->em))->pageMetadata($space, new WikiPageMetadataQuery());
        self::assertSame(['alice'], array_map(static fn ($row): ?string => $row->authorId, $metadata));
    }

    public function testAnIdThatCannotBeMatchedExactlyIsNeverWritten(): void
    {
        $this->setUpSchema(null);
        [$page] = $this->page(new WikiStoredUserRef('alice'));
        $this->em->persist(new WikiPageRevision($page, 2, '<p>x</p>', new TestUser('bob ')));

        $this->expectException(InvalidArgumentException::class);
        $this->em->flush();
    }

    #[DataProvider('columnCollations')]
    public function testLookalikeIdsNeverReachAnotherUsersComments(?string $collation): void
    {
        $this->setUpSchema($collation);
        [$page] = $this->page(new WikiStoredUserRef('bob'));
        $repo   = new DoctrineOrmWikiCommentRepository($this->em);
        $mine   = $this->thread($repo, $page, 'bob');
        foreach (self::LOOKALIKES as $lookalike) {
            $this->thread($repo, $page, $lookalike);
        }
        $repo->flush();
        $this->em->clear();
        $page = $this->em->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $page);
        $bob = new WikiStoredUserRef('bob');

        self::assertSame([$mine[1]], array_map(static fn (WikiComment $c): string => $c->getId(), $repo->byAuthor($bob)));
        $started = array_map(static fn (WikiCommentThread $t): string => $t->getId(), $repo->threadsBy($bob, 'createdBy'));
        sort($started);
        $expectedStarted = [$mine[0], $mine[3]];
        sort($expectedStarted);
        self::assertSame($expectedStarted, $started);
        self::assertSame([$mine[0]], array_map(static fn (WikiCommentThread $t): string => $t->getId(), $repo->threadsBy($bob, 'resolvedBy')));
        self::assertSame([$mine[2]], array_map(static fn (WikiCommentReaction $r): string => $r->getId(), $repo->reactionsBy($bob)));
        self::assertSame(1, $repo->countOpenBy($page, new TestUser('bob')));
        $comment = $this->em->find(WikiComment::class, $mine[1]);
        self::assertInstanceOf(WikiComment::class, $comment);
        self::assertSame($mine[2], $repo->reaction($comment, $bob, '+1')?->getId());
        self::assertNull($repo->reaction($comment, new WikiStoredUserRef('Bob'), '+1'));
        self::assertNull($repo->reaction($comment, new TestUser('bob '), '+1'));
        self::assertSame([], $repo->byAuthor(new TestUser("bob\n")));
        self::assertSame([], $repo->reactionsBy(new TestUser("bob\u{200B}")));
        self::assertSame([], $repo->threadsBy(new TestUser("bob\u{A0}"), 'createdBy'));
        self::assertSame(0, $repo->countOpenBy($page, new TestUser(' bob')));

        // The erasure of bob leaves every look-alike row as it was.
        self::assertSame(1, $repo->eraseAuthor($bob, true));
        $this->em->clear();
        self::assertSame([], $repo->byAuthor($bob));
        self::assertSame([], $repo->reactionsBy($bob));
        self::assertSame([], $repo->threadsBy($bob, 'createdBy'));
        self::assertSame([], $repo->threadsBy($bob, 'resolvedBy'));
        foreach (self::LOOKALIKES as $lookalike) {
            $ref = new WikiStoredUserRef($lookalike);
            self::assertCount(1, $repo->byAuthor($ref), $lookalike);
            self::assertCount(1, $repo->reactionsBy($ref), $lookalike);
            self::assertCount(2, $repo->threadsBy($ref, 'createdBy'), $lookalike);
            self::assertCount(1, $repo->threadsBy($ref, 'resolvedBy'), $lookalike);
        }
        $ids = array_values(array_filter(array_map(static fn (WikiComment $c): ?string => $c->getAuthor()?->getId(), $this->em->getRepository(WikiComment::class)->findAll())));
        sort($ids);
        $expected = [...self::LOOKALIKES];
        sort($expected);
        self::assertSame($expected, $ids, 'bob\'s comment is unlinked, the look-alikes keep their author');

        // Nothing to erase for an id that cannot be matched exactly.
        self::assertSame(0, $repo->eraseAuthor(new TestUser('bob '), true));
    }

    private function setUpSchema(?string $collation): void
    {
        $this->em = WikiTestDatabase::create(null, WikiMetadataListener::USER_REFERENCE_ID);
        if ($collation === null) {
            return;
        }
        if (!$this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('A folding collation exists only on MySQL / MariaDB.');
        }
        foreach (self::COLUMNS as [$table, $column, $nullable]) {
            $this->em->getConnection()->executeStatement('ALTER TABLE ' . $table . ' MODIFY ' . $column . ' VARCHAR(128) CHARACTER SET utf8mb4 COLLATE ' . $collation . ($nullable ? ' NULL' : ' NOT NULL'));
        }
    }

    /**
     * @return array{WikiPage, WikiPageRevision}
     */
    private function page(object $author): array
    {
        $space    = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page     = new WikiPage($space, 'home', 'Home');
        $revision = new WikiPageRevision($page, 1, '<p>x</p>', $author);
        $page->setCurrentRevision($revision);
        $this->em->persist($space);
        $this->em->persist($page);
        $this->em->persist($revision);
        $this->em->flush();

        return [$page, $revision];
    }

    /**
     * A resolved thread opened and resolved by the user, with one comment and one reaction of theirs.
     *
     * @return array{string, string, string, string} thread, comment, reaction and open thread ids
     */
    private function thread(DoctrineOrmWikiCommentRepository $repo, WikiPage $page, string $id): array
    {
        $user     = new WikiStoredUserRef($id);
        $thread   = new WikiCommentThread($page, $user, null, null);
        $comment  = new WikiComment($thread, $user, 'Body of ' . $id, 0);
        $reaction = new WikiCommentReaction($comment, $user, '+1');
        $open     = new WikiCommentThread($page, $user, null, null);
        $thread->resolve($user);
        foreach ([$thread, $comment, $reaction, $open] as $entity) {
            $repo->add($entity);
        }

        return [$thread->getId(), $comment->getId(), $reaction->getId(), $open->getId()];
    }

    /** @return class-string */
    private function classOf(string $table): string
    {
        return match ($table) {
            'wiki_page_revisions'  => WikiPageRevision::class,
            'wiki_page_links'      => WikiPageLink::class,
            'wiki_attachments'     => WikiAttachment::class,
            'wiki_comment_threads' => WikiCommentThread::class,
            'wiki_comments'        => WikiComment::class,
            default                => WikiCommentReaction::class,
        };
    }
}
