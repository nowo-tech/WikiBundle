<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Database;

use Doctrine\DBAL\Driver\Exception as DriverExceptionInterface;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\WikiBundle\Attachment\WikiAttachmentRejectedException;
use Nowo\WikiBundle\Collaboration\DoctrineWikiCollabStateStore;
use Nowo\WikiBundle\Collaboration\WikiCollabUnitLostException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\LoadReadinessFactory;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function file_put_contents;
use function str_repeat;
use function str_starts_with;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;

/**
 * 1.37, audit QN: the proofs of concept of the audit, inverted (they pass only with the fixes), on a manager given by
 * hand. 2.0 removed the shared entity manager, and with it the QN-M1 close and the QN-L1 / QN-L2 forgetting of a
 * failed unit's wiki rows (covered for the private manager by the private entity manager tests). SQLite by default; the
 * server-only ones with WIKI_TEST_DATABASE_URL (MySQL).
 */
final class LoadReadinessQnTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $author;

    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->author = new TestUserEntity('u-qn');
        $this->space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->author);
        $this->em->persist($this->space);
        $this->em->flush();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->close();
    }

    /** QN-L3 (MySQL): inside the application's REPEATABLE READ transaction the quota still holds. */
    public function testTheQuotaHoldsInsideTheApplicationsTransaction(): void
    {
        if (!WikiTestDatabase::isServer() || !str_starts_with((string) WikiTestDatabase::url(), 'mysql')) {
            self::markTestSkipped('Needs MySQL in ' . WikiTestDatabase::ENV . '.');
        }
        $page      = $this->page();
        $directory = sys_get_temp_dir() . '/wiki-qn-' . uniqid();
        $other     = WikiTestDatabase::connect();
        $otherPage = $other->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $otherPage);
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        $connection->fetchOne('SELECT COUNT(*) FROM wiki_attachments'); // the snapshot is taken here
        LoadReadinessFactory::attachments($other, $directory, 100)->upload($otherPage, $this->file(60), 'a.txt');

        try {
            LoadReadinessFactory::attachments($this->em, $directory, 100)->upload($page, $this->file(60), 'b.txt');
            self::fail('The quota must hold.');
        } catch (WikiAttachmentRejectedException $refused) {
            self::assertSame(WikiAttachmentRejectedException::QUOTA_EXCEEDED, $refused->reason);
        } finally {
            $connection->commit();
            $other->getConnection()->close();
        }
        self::assertSame(60, (int) $connection->fetchOne('SELECT COALESCE(SUM(size), 0) FROM wiki_attachments'));
    }

    /** QN-L4: a transient failure of the co-editing log inside a transaction it did not open is never swallowed. */
    public function testATransientFailureOfTheLogInsideATransactionIsTheWholeUnits(): void
    {
        $store = new DoctrineWikiCollabStateStore($this->em);
        $store->reset('p1', null);
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $store->whileLocked('p1', static function (): never {
                throw new DeadlockException(new class('Deadlock found', 1213) extends RuntimeException implements DriverExceptionInterface {
                    public function getSQLState(): string
                    {
                        return '40001';
                    }
                }, null);
            });
            self::fail('Expected the lost unit.');
        } catch (Throwable $failure) {
            self::assertInstanceOf(WikiCollabUnitLostException::class, $failure);
            self::assertInstanceOf(TransientStorageFailure::class, $failure->getPrevious());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        }
    }

    /** The log's own outermost transaction: a transient failure stays a TransientStorageFailure (retried as before). */
    public function testATransientFailureOfTheLogsOwnTransactionStaysTransient(): void
    {
        $store = new DoctrineWikiCollabStateStore($this->em);
        $store->reset('p1', null);

        $this->expectException(TransientStorageFailure::class);
        $store->whileLocked('p1', static function (): never {
            throw new DeadlockException(new class('Deadlock found', 1213) extends RuntimeException implements DriverExceptionInterface {
                public function getSQLState(): string
                {
                    return '40001';
                }
            }, null);
        });
    }

    /** QN-I2: a page purged meanwhile is not linked. */
    public function testALinkToAPagePurgedMeanwhileIsRefused(): void
    {
        $page = $this->page();
        $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET current_revision_id = NULL WHERE id = ?', [$page->getId()]);
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_page_revisions WHERE page_id = ?', [$page->getId()]);
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_pages WHERE id = ?', [$page->getId()]);

        $this->expectException(InvalidArgumentException::class);
        LoadReadinessFactory::links($this->em)->link($page, 'task', 'T-9', $this->author);
    }

    private function page(): WikiPage
    {
        return LoadReadinessFactory::pages($this->em)->create($this->space, 'Runbook', 'base', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
    }

    private function file(int $bytes): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'wiki-qn');
        file_put_contents($file, str_repeat('a', $bytes));

        return $file;
    }
}
