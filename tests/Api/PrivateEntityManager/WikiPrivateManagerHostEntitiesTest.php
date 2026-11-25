<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\PrivateEntityManager;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Service\WikiPageNotFoundException;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageServiceInterface;
use Nowo\WikiBundle\Space\WikiSpaceNotFoundException;
use Nowo\WikiBundle\Tests\Api\App\ApiKernel;
use Nowo\WikiBundle\Tests\Stub\TestHostBookmark;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

use function assert;

/**
 * 2.0 (REQ-ARCH-001 rule 6, wave-1 lesson W1-2): with the private entity manager as the only mode, host entities and
 * wiki rows cross the two managers safely, on every database of the matrix (`WIKI_TEST_DATABASE_URL`):
 *
 * - a host user the application holds (initialised or an uninitialised proxy) becomes the private manager's reference;
 *   the event hands the application its own instance;
 * - a host user persisted but not flushed fails clearly before anything is written, and the host's insert stays pending;
 * - a wiki row loaded through the application's manager is loaded again by id: same result, no duplicate row;
 * - the application's flush of its own entity that points at a wiki row through getReference() writes the foreign key;
 * - the wiki never flushes, clears or detaches the application's manager.
 */
final class WikiPrivateManagerHostEntitiesTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new ApiKernel();
    }

    #[Test]
    public function aHostHeldUserIsWrittenAsAReferenceAndTheEventKeepsTheHostInstance(): void
    {
        $host = $this->host();
        $page = $this->privatePage($this->page('held')->getId());
        $user = new TestUserEntity('held-user');
        $host->persist($user);
        $host->flush();

        $seen = [];
        $this->dispatcher()->addListener(WikiEvents::PAGE_SAVED, static function (WikiPageSavedEvent $event) use (&$seen): void {
            $seen[] = $event->actor;
        });
        $revision = $this->pages()->saveRevision($page, 'Held', '<p>held</p>', $user);

        self::assertSame([$user], $seen, 'the event hands the application its own user');
        self::assertTrue($host->contains($user));
        self::assertSame(1, $this->rows('wiki_page_revisions', 'author_id', 'held-user'));
        self::assertSame(2, $revision->getRevisionNumber());

        // An uninitialised proxy of the application's manager: its id is read from the unit of work, never loaded.
        $host->clear();
        $proxy = $host->getReference(TestUserEntity::class, 'held-user');
        self::assertNotNull($proxy);
        self::assertTrue($host->getUnitOfWork()->isUninitializedObject($proxy));
        $this->pages()->saveRevision($this->privatePage($page->getId()), 'Proxy', '<p>proxy</p>', $proxy);
        self::assertSame(2, $this->rows('wiki_page_revisions', 'author_id', 'held-user'));
        self::assertTrue($host->getUnitOfWork()->isUninitializedObject($proxy), 'the wiki never loads the application\'s proxy');
    }

    #[Test]
    public function aHostUserWithoutAnIdentifierFailsBeforeAnythingIsWritten(): void
    {
        $host    = $this->host();
        $page    = $this->page('unsaved');
        $pending = new TestUserEntity('author-pending');
        $host->persist($pending);
        $before = $this->rows('wiki_page_revisions', 'page_id', $page->getId());

        // The application's user is persisted but not flushed: the wiki write fails before anything is written.
        try {
            $this->pages()->saveRevision($this->privatePage($page->getId()), 'Unsaved', '<p>x</p>', $pending);
            self::fail('A user the application did not flush yet must be refused.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('Flush your', $exception->getMessage());
        }
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($pending), 'the application\'s insert stays pending');

        $private = $this->private();
        $space   = new WikiSpace('no-id', 'No id', WikiSpaceOwnerScope::Team, 'team-1');
        $orphan  = new WikiPage($space, 'orphan', 'Orphan');
        $private->persist($space);
        $private->persist($orphan);
        $private->persist(new WikiPageRevision($orphan, 1, '<p>x</p>', $pending));
        try {
            $private->flush();
            self::fail('A user without a row must be refused.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('has no identifier yet', $exception->getMessage());
        }
        self::assertSame(0, $this->rows('wiki_spaces', 'slug', 'no-id'), 'nothing was written');
        self::assertSame($before, $this->rows('wiki_page_revisions', 'page_id', $page->getId()));
        self::assertTrue($host->getUnitOfWork()->isScheduledForInsert($pending));
        $private->clear();
    }

    #[Test]
    public function aWikiRowLoadedByTheHostIsLoadedAgainAndNeverDuplicated(): void
    {
        $host  = $this->host();
        $user  = $this->user('author-reload');
        $page  = $this->page('reload');
        $pages = $this->rows('wiki_pages', 'id', $page->getId());

        $foreign = $host->find(WikiPage::class, $page->getId());
        assert($foreign instanceof WikiPage);
        $revision = $this->pages()->saveRevision($foreign, 'Reloaded', '<p>reloaded</p>', $user);
        $this->pages()->archive($foreign, $user);

        self::assertSame($pages, $this->rows('wiki_pages', 'id', $page->getId()), 'no duplicate row');
        self::assertSame(2, $revision->getRevisionNumber());
        $row = $host->getConnection()->fetchAssociative('SELECT title, current_revision_id, archived_at FROM wiki_pages WHERE id = ?', [$page->getId()]);
        self::assertIsArray($row);
        self::assertSame('Reloaded', $row['title']);
        self::assertSame($revision->getId(), $row['current_revision_id']);
        self::assertNotNull($row['archived_at']);
        self::assertTrue($host->contains($foreign), 'the application\'s instance stays the application\'s');

        // Persisting, refreshing or locking the application's instance through the wiki's manager is refused clearly.
        foreach (['persist', 'refresh'] as $operation) {
            try {
                $this->private()->{$operation}($foreign);
                self::fail($operation);
            } catch (LogicException $exception) {
                self::assertStringContainsString('loaded through your entity manager', $exception->getMessage());
            }
        }

        // A row gone meanwhile answers the bundle's not-found exception.
        $host->getConnection()->executeStatement('DELETE FROM wiki_page_revisions WHERE page_id = ?', [$page->getId()]);
        $host->getConnection()->executeStatement('DELETE FROM wiki_pages WHERE id = ?', [$page->getId()]);
        $this->private()->clear();
        $this->expectException(WikiPageNotFoundException::class);
        $this->runner()->attach($foreign);
    }

    #[Test]
    public function aGoneSpaceAnswersTheSpaceNotFoundException(): void
    {
        $host  = $this->host();
        $page  = $this->page('gone-space');
        $space = $host->find(WikiSpace::class, $page->getSpace()->getId());
        assert($space instanceof WikiSpace);
        $attached = $this->runner()->attach($space);
        self::assertNotSame($space, $attached, 'a host-held space is loaded again in the private manager');
        self::assertSame($space->getId(), $attached->getId());

        $host->getConnection()->executeStatement('DELETE FROM wiki_page_revisions');
        $host->getConnection()->executeStatement('DELETE FROM wiki_pages');
        $host->getConnection()->executeStatement('DELETE FROM wiki_spaces WHERE id = ?', [$space->getId()]);
        $this->private()->clear();
        $this->expectException(WikiSpaceNotFoundException::class);
        $this->runner()->attach($space);
    }

    #[Test]
    public function theHostWritesItsForeignKeyAndKeepsItsPendingChanges(): void
    {
        $host     = $this->host();
        $page     = $this->page('bookmark');
        $bookmark = new TestHostBookmark('bookmark-1', 'before');
        $bookmark->setPage($host->getReference(WikiPage::class, $page->getId()));
        $host->persist($bookmark);
        $host->flush();
        self::assertSame($page->getId(), $host->getConnection()->fetchOne('SELECT page_id FROM test_host_bookmarks WHERE id = ?', ['bookmark-1']));

        // A host change left pending is not written by a wiki write, and stays pending with its value.
        $author = $this->user('author-bookmark');
        $bookmark->setNote('after');
        $this->pages()->saveRevision($this->privatePage($page->getId()), 'Pending', '<p>pending</p>', $author);
        self::assertSame('before', $host->getConnection()->fetchOne('SELECT note FROM test_host_bookmarks WHERE id = ?', ['bookmark-1']));
        self::assertTrue($host->contains($bookmark));
        self::assertSame('after', $bookmark->getNote());
        $host->getUnitOfWork()->computeChangeSets();
        self::assertSame(['note' => ['before', 'after']], $host->getUnitOfWork()->getEntityChangeSet($bookmark));
        $host->flush();
        self::assertSame('after', $host->getConnection()->fetchOne('SELECT note FROM test_host_bookmarks WHERE id = ?', ['bookmark-1']));
    }

    private function rows(string $table, string $column, string $value): int
    {
        return (int) $this->host()->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?', [$value]);
    }

    private function user(string $id): TestUserEntity
    {
        $host = $this->host();
        $user = $host->find(TestUserEntity::class, $id) ?? new TestUserEntity($id);
        $host->persist($user);
        $host->flush();

        return $user;
    }

    /** A space and a page written through the wiki (one revision), then forgotten by the private manager. */
    private function page(string $slug): WikiPage
    {
        $private = $this->private();
        $space   = new WikiSpace($slug, 'Space ' . $slug, WikiSpaceOwnerScope::Team, 'team-1');
        $private->persist($space);
        $private->flush();
        $page = $this->pages()->create($space, 'Page ' . $slug, '<p>v1</p>', $this->user('author-' . $slug));
        $private->clear();

        return $page;
    }

    private function privatePage(string $id): WikiPage
    {
        $page = $this->private()->find(WikiPage::class, $id);
        assert($page instanceof WikiPage);

        return $page;
    }

    private function host(): EntityManagerInterface
    {
        $doctrine = $this->doctrine();
        $host     = $doctrine->getManager();
        assert($host instanceof EntityManagerInterface);

        return $host;
    }

    private function private(): WikiEntityManager
    {
        $provider = self::getContainer()->get(WikiEntityManagerProvider::class);
        assert($provider instanceof WikiEntityManagerProvider);
        $private = $provider->get();
        assert($private instanceof WikiEntityManager);

        return $private;
    }

    private function pages(): WikiPageServiceInterface
    {
        $pages = self::getContainer()->get(WikiPageServiceInterface::class);
        assert($pages instanceof WikiPageServiceInterface);

        return $pages;
    }

    private function runner(): WikiTransactionRunner
    {
        $runner = self::getContainer()->get(WikiTransactionRunner::class);
        assert($runner instanceof WikiTransactionRunner);

        return $runner;
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = self::getContainer()->get('event_dispatcher');
        assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }

    private bool $schema = false;

    private function doctrine(): ManagerRegistry
    {
        if (!self::$booted) {
            self::bootKernel();
        }
        $doctrine = self::getContainer()->get('doctrine');
        assert($doctrine instanceof ManagerRegistry);
        if (!$this->schema) {
            $em = $doctrine->getManager();
            assert($em instanceof EntityManagerInterface);
            $tool     = new SchemaTool($em);
            $metadata = $em->getMetadataFactory()->getAllMetadata();
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
            $this->schema = true;
        }

        return $doctrine;
    }
}
