<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Nowo\MilkdownEditorBundle\Comment\CommentAnchor;
use Nowo\WikiBundle\Attachment\WikiAttachmentRejectedException;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Comment\WikiCommentException;
use Nowo\WikiBundle\Entity\WikiAttachment;
use Nowo\WikiBundle\Entity\WikiCollabUpdate;
use Nowo\WikiBundle\Entity\WikiCommentThread;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiAttachmentRepository;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sys_get_temp_dir;
use function uniqid;

/**
 * 1.37 (Beacon spec 239): the write paths of the transaction runner that the race tests do not reach (SQLite).
 */
final class LoadReadinessPathsTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $author;

    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->author = new TestUserEntity('u-paths');
        $this->space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->author);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    public function testARestoreAndAConversionRunUnderThePageLock(): void
    {
        $pages = LoadReadinessFactory::pages($this->em);
        $html  = $pages->create($this->space, 'Html', '<p>one</p>', $this->author);
        $first = $html->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $first);
        $pages->saveRevision($html, 'Html', '<p>two</p>', $this->author);
        self::assertSame(3, $pages->restore($html, $first, $this->author)->getRevisionNumber());
        self::assertSame(4, $pages->convertToMarkdown($html, 'one', $this->author)->getRevisionNumber());

        $markdown = $pages->create($this->space, 'Md', 'one', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $base     = $markdown->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $base);
        $pages->saveRevision($markdown, 'Md', 'two', $this->author, WikiPageRevision::FORMAT_MARKDOWN);
        $restored = $pages->restore($markdown, $base, $this->author);
        self::assertSame('one', $restored->getContentMarkdown());
        $pages->unarchive($markdown);
        $pages->archive($markdown);
        $pages->unarchive($markdown);
        self::assertFalse($markdown->isArchived());
    }

    public function testAnAnchorMovedMeanwhileIsStaleUnderTheLock(): void
    {
        $page     = $this->page();
        $comments = LoadReadinessFactory::comments($this->em, $this->space);
        $thread   = $comments->create($this->author, $this->space, $page, $this->anchor(), 'inline');
        // Another request moved it: this request still holds the thread as it loaded it.
        $this->em->getConnection()->executeStatement('UPDATE wiki_comment_threads SET updated_at = ? WHERE id = ?', [(new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'), $thread['id']]);

        try {
            $comments->patchThread($this->author, $this->space, $page, (string) $thread['id'], ['anchor' => $this->anchor('daily', 10), 'base' => $thread['updatedAt']]);
            self::fail('A stale base must be refused.');
        } catch (WikiCommentException $refused) {
            self::assertSame([409, 'stale'], [$refused->status, $refused->reason]);
        }
    }

    public function testACommentOnAPageGoneMeanwhileIsNotFound(): void
    {
        $page = $this->page();
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_pages WHERE id = ?', [$page->getId()]);

        try {
            LoadReadinessFactory::comments($this->em, $this->space)->create($this->author, $this->space, $page, null, 'late');
            self::fail('Expected a 404.');
        } catch (WikiCommentException $refused) {
            self::assertSame(404, $refused->status);
        }
    }

    public function testACommentDeletedWhileWaitingForTheThreadLockIsNotFound(): void
    {
        $page     = $this->page();
        $comments = LoadReadinessFactory::comments($this->em, $this->space);
        $thread   = $comments->create($this->author, $this->space, $page, null, 'first');
        $comments->reply($this->author, $this->space, $page, (string) $thread['id'], 'second');
        $comment    = (string) $this->em->getConnection()->fetchOne('SELECT id FROM wiki_comments WHERE position = 1');
        $connection = $this->em->getConnection();
        // The other request deletes the comment while this one waits for the thread's lock.
        $this->em->getEventManager()->addEventListener(Events::postLoad, new class($connection, $comment) {
            private bool $done = false;

            public function __construct(private readonly Connection $connection, private readonly string $comment)
            {
            }

            public function postLoad(PostLoadEventArgs $args): void
            {
                if (!$this->done && $args->getObject() instanceof WikiCommentThread) {
                    $this->done = true;
                    $this->connection->executeStatement('DELETE FROM wiki_comments WHERE id = ?', [$this->comment]);
                }
            }
        });

        try {
            $comments->editComment($this->author, $this->space, $page, $comment, 'edited');
            self::fail('Expected a 404.');
        } catch (WikiCommentException $refused) {
            self::assertSame(404, $refused->status);
        }
    }

    public function testAnUploadOverTheQuotaUnderTheLockIsRefusedAndItsFileRemoved(): void
    {
        $page       = $this->page();
        $directory  = sys_get_temp_dir() . '/wiki-paths-' . uniqid();
        $inner      = new DoctrineOrmWikiAttachmentRepository($this->em);
        $repository = new class($inner) implements WikiAttachmentRepositoryInterface {
            public int $sums = 0;

            public function __construct(private readonly WikiAttachmentRepositoryInterface $inner)
            {
            }

            public function save(WikiAttachment $attachment): void
            {
                $this->inner->save($attachment);
            }

            public function remove(WikiAttachment $attachment): void
            {
                $this->inner->remove($attachment);
            }

            public function findById(string $id): ?WikiAttachment
            {
                return $this->inner->findById($id);
            }

            public function findByIds(array $ids): array
            {
                return $this->inner->findByIds($ids);
            }

            public function findByPage(WikiPage $page): array
            {
                return $this->inner->findByPage($page);
            }

            public function sumSizeBySpace(WikiSpace $space): int
            {
                // The check before the lock saw an empty space; another upload filled it meanwhile.
                return ++$this->sums % 2 === 1 ? 0 : 10;
            }

            public function findUploadedBefore(DateTimeImmutable $before, int $limit = 500): array
            {
                return $this->inner->findUploadedBefore($before, $limit);
            }

            public function existsByStorageKey(string $storageKey): bool
            {
                return $this->inner->existsByStorageKey($storageKey);
            }

            public function isReferencedByPageRevisions(WikiPage $page, string $needle): bool
            {
                return $this->inner->isReferencedByPageRevisions($page, $needle);
            }
        };
        $service = LoadReadinessFactory::attachments($this->em, $directory, 15, $repository);

        try {
            $service->upload($page, $this->file(), 'note.txt');
            self::fail('Expected the quota refusal.');
        } catch (WikiAttachmentRejectedException $refused) {
            self::assertSame(WikiAttachmentRejectedException::QUOTA_EXCEEDED, $refused->reason);
        }
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM wiki_attachments'));

        $accepted = LoadReadinessFactory::attachments($this->em, $directory, 15)->upload($page, $this->file(), 'note.txt');
        self::assertSame(10, $accepted->getSize());
    }

    public function testTheCollabStoreKeepsOtherDriverFailuresAndUsesASequenceWhenMapped(): void
    {
        $store = new DoctrineWikiCollabStateStore($this->em);
        $store->reset('p1', null);
        $driver = new class('Unknown column', 1054) extends RuntimeException implements DriverExceptionInterface {
            public function getSQLState(): string
            {
                return '42S22';
            }
        };
        $failure = new DriverException($driver, null);
        try {
            $store->whileLocked('p1', static function () use ($failure): void {
                throw $failure;
            });
            self::fail('Expected the failure.');
        } catch (DriverException $caught) {
            self::assertSame($failure, $caught);
        }

        // A sequence generator (PostgreSQL with the SEQUENCE strategy): the id is taken first and inserted with the row.
        $metadata = $this->em->getClassMetadata(WikiCollabUpdate::class);
        $original = $metadata->idGenerator;
        $metadata->setIdGenerator(new class extends AbstractIdGenerator {
            public function generateId(EntityManagerInterface $em, ?object $entity): mixed
            {
                return 4242;
            }
        });
        try {
            self::assertSame(4242, $store->append('p1', 'dQ=='));
        } finally {
            $metadata->setIdGenerator($original);
        }
        self::assertInstanceOf(ClassMetadata::class, $metadata);
    }

    public function testARevisionComputesItsPlainTextAgain(): void
    {
        $revision = new WikiPageRevision(new WikiPage($this->space, 'p', 'P'), 1, '<p>Hello</p>', $this->author);
        $revision->refreshContentText();
        self::assertFalse($revision->refreshContentText());
    }

    private function page(): WikiPage
    {
        return LoadReadinessFactory::pages($this->em)->create($this->space, 'Runbook', 'We deploy daily', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    private function file(): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'wiki-paths');
        file_put_contents($file, '0123456789');

        return $file;
    }

    /**
     * @return array<string, mixed>
     */
    private function anchor(string $exact = 'deploy', int $start = 3): array
    {
        return [
            'v'        => 1,
            'quote'    => ['exact' => $exact, 'prefix' => 'We ', 'suffix' => ' daily'],
            'position' => ['start' => $start, 'end' => $start + CommentAnchor::length($exact)],
            'block'    => ['index' => 0, 'heading' => null],
        ];
    }
}
