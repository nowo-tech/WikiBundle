<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Link\WikiPageLinkOperationsInterface;
use Nowo\WikiBundle\Port\WikiUserRefs;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Query\WikiOwnerUsage;
use Nowo\WikiBundle\Query\WikiPageCriteria;
use Nowo\WikiBundle\Query\WikiPageQueryInterface;
use Nowo\WikiBundle\Query\WikiPageSearchHit;
use Nowo\WikiBundle\Query\WikiSpaceCriteria;
use Nowo\WikiBundle\Query\WikiSpaceQueryInterface;
use Nowo\WikiBundle\Repository\WikiPageProvenance;
use Nowo\WikiBundle\Repository\WikiPageProvenanceFinderInterface;
use Nowo\WikiBundle\Security\WikiPageAccessInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageOperationsInterface;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiSpaceOperationsInterface;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function array_map;
use function assert;
use function gc_collect_cycles;
use function memory_get_usage;
use function sprintf;
use function substr_count;

/**
 * 1.36.0 (REQ-ARCH-001, "both host kinds are tested"): the DDD host path end to end. The standard host path is the rest of
 * the suite (attribute mapping, a Doctrine `user_class`, the deprecated ports, the application's manager).
 *
 * The host ({@see DddHostKernel}) has no user table, no bundle base class or trait, maps the wiki from XML, stores its
 * users by id and answers every port through its framework-free twin; the wiki writes through its private entity
 * manager. The host writes and reads through the id operations, and its people use the web UI in one kept-alive
 * kernel (`http_kernel->handle()` + `terminate()`, no services resetter).
 */
final class DddHostKernelTest extends WebTestCase
{
    private const ROUNDS = 30;

    private const MEMORY_MARGIN = 2 * 1024 * 1024;

    private KernelBrowser $client;

    protected static function createKernel(array $options = []): KernelInterface
    {
        return new DddHostKernel();
    }

    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove((new DddHostKernel())->getProjectDir());
    }

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        assert($em instanceof EntityManagerInterface);
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        DddPolicies::$asked = [];
        DddBadges::$asked   = [];
    }

    public function testTheHostMapsNothingOfItsOwnAndTheWikiComesFromXmlWithUserIds(): void
    {
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        assert($em instanceof EntityManagerInterface);
        $classes = array_map(static fn (ClassMetadata $metadata): string => $metadata->getName(), $em->getMetadataFactory()->getAllMetadata());
        foreach ($classes as $class) {
            self::assertStringStartsWith('Nowo\\WikiBundle\\Entity\\', $class, 'only wiki entities are mapped');
        }
        // The XML driver itself: ApiXmlMappingTest; XmlMappingParityTest proves the same metadata.
        $revision = $em->getClassMetadata(WikiPageRevision::class);
        self::assertFalse($revision->hasAssociation('author'), 'no relation to a host user');
        self::assertSame('author_id', $revision->getColumnName('author'));
        $connection = $em->getConnection();
        $table      = $connection->createSchemaManager()->introspectTable($revision->getTableName());
        self::assertTrue($table->hasColumn('author_id'));
        foreach ($table->getForeignKeys() as $foreignKey) {
            self::assertNotContains('author_id', $foreignKey->getLocalColumns(), 'no foreign key to a user table');
        }
    }

    public function testTheHostWritesAndReadsThroughTheIdOperationsOnly(): void
    {
        $container = self::getContainer();
        $spaces    = $container->get(WikiSpaceOperationsInterface::class);
        $pages     = $container->get(WikiPageOperationsInterface::class);
        $links     = $container->get(WikiPageLinkOperationsInterface::class);
        $events    = $container->get('event_dispatcher');
        assert($spaces instanceof WikiSpaceOperationsInterface && $pages instanceof WikiPageOperationsInterface && $links instanceof WikiPageLinkOperationsInterface && $events instanceof EventDispatcherInterface);
        $saved = [];
        $events->addListener(WikiEvents::PAGE_SAVED, static function (WikiPageSavedEvent $event) use (&$saved): void {
            $saved[] = [$event->pageId(), $event->revisionSnapshot()->revisionNumber, $event->actorId(), $event->actorRef()?->id];
        });
        $ana = WikiUserRef::ofId(DddUser::class, 'u-ana');
        $bob = WikiUserRef::ofId(DddUser::class, 'u-bob');

        $space = $spaces->createSpace('Docs', WikiSpaceOwnerScope::Team, 'team-a', 'docs');
        $page  = $pages->createPage($space->id, 'Home', '# Home', $ana, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $next  = $pages->saveRevision($page->id, 'Home', '# Home v2', $bob, WikiPageRevision::FORMAT_MARKDOWN, 1);
        $links->linkPage($page->id, 'ticket', 'T-1', $ana);

        self::assertSame(2, $next->revisionNumber);
        self::assertSame('u-bob', $next->authorId);
        self::assertSame([[$page->id, 1, 'u-ana', 'u-ana'], [$page->id, 2, 'u-bob', 'u-bob']], $saved, 'events carry ids and refs, after the write');
        self::assertSame($next->id, $pages->findPage($page->id)?->currentRevisionId);
        self::assertSame('docs', $spaces->findSpaceBySlug(WikiSpaceOwnerScope::Team, 'team-a', 'docs')?->slug);
        self::assertNull($spaces->findSpaceBySlug(WikiSpaceOwnerScope::Team, 'Team-a', 'docs'), 'look-alike owner: nothing');

        // The stored authors are ids (no user table, no foreign key) read back as WikiStoredUserRef.
        $connection = $container->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);
        self::assertSame(['u-ana', 'u-bob'], $connection->fetchFirstColumn('SELECT author_id FROM wiki_page_revisions ORDER BY revision_number'));
        $wiki = $container->get(WikiEntityManagerProvider::class);
        assert($wiki instanceof WikiEntityManagerProvider);
        $private = $wiki->get();
        self::assertInstanceOf(WikiEntityManager::class, $private);
        $revision = $private->find(WikiPageRevision::class, $next->id);
        self::assertInstanceOf(WikiPageRevision::class, $revision);
        self::assertInstanceOf(WikiStoredUserRef::class, $revision->getAuthor());
        self::assertSame('u-bob', $revision->getAuthor()->getId());

        // A look-alike user is another user; an unusable id never reaches the database.
        $lookalike = $pages->saveRevision($page->id, 'Home', '# Look-alike', WikiUserRef::ofId(DddUser::class, 'U-ana'), WikiPageRevision::FORMAT_MARKDOWN, 2);
        self::assertSame('U-ana', $lookalike->authorId);
        $this->expectException(InvalidArgumentException::class);
        WikiUserRef::ofId(DddUser::class, 'u-ana ');
    }

    public function testPeopleUseTheWebUiInAKeptAliveKernelWithoutTheApplicationsManager(): void
    {
        $container = self::getContainer();
        $spaces    = $container->get(WikiSpaceOperationsInterface::class);
        $pages     = $container->get(WikiPageOperationsInterface::class);
        assert($spaces instanceof WikiSpaceOperationsInterface && $pages instanceof WikiPageOperationsInterface);
        $docs = $spaces->createSpace('Docs', WikiSpaceOwnerScope::Team, 'team-a', 'docs');
        $ops  = $spaces->createSpace('Ops', WikiSpaceOwnerScope::Team, 'team-b', 'ops');
        $pages->createPage($docs->id, 'Ana page', 'Ana text', WikiUserRef::ofId(DddUser::class, 'u-ana'), null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $pages->createPage($ops->id, 'Bob page', 'Bob text', WikiUserRef::ofId(DddUser::class, 'u-bob'), null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $wiki = $container->get(WikiEntityManagerProvider::class);
        assert($wiki instanceof WikiEntityManagerProvider);
        $private = $wiki->get();
        self::assertInstanceOf(WikiEntityManager::class, $private);
        $private->reset();
        $host = $container->get('doctrine.orm.entity_manager');
        assert($host instanceof EntityManagerInterface);

        $people   = ['u-ana' => $this->signIn('u-ana'), 'u-bob' => $this->signIn('u-bob')];
        $expected = ['u-ana' => ['docs' => 200, 'ops' => 404], 'u-bob' => ['docs' => 404, 'ops' => 200]];
        $baseline = 0;
        for ($round = 0; $round < self::ROUNDS; ++$round) {
            foreach ($expected as $who => $answers) {
                foreach ($answers as $slug => $status) {
                    $response = $this->raw('/tools/wiki/spaces/' . $slug, $people[$who]);
                    self::assertSame($status, $response->getStatusCode(), sprintf('round %d: %s on %s', $round, $who, $slug));
                    if ($status === 200) {
                        self::assertStringContainsString($slug === 'docs' ? 'Ana page' : 'Bob page', (string) $response->getContent());
                    }
                    self::assertFalse($private->isCreated(), 'the private manager is dropped at every request end');
                    self::assertSame([], $host->getUnitOfWork()->getIdentityMap(), 'the application\'s manager never holds a wiki row');
                }
            }
            if ($round === 5) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }
        gc_collect_cycles();
        self::assertLessThan(self::MEMORY_MARGIN, memory_get_usage() - $baseline, 'flat memory over the run');
        self::assertNotSame([], DddPolicies::$asked, 'the twin space policy decided');
    }

    /**
     * 1.35 "create at a position" on the DDD host (1.36.0): by ids, under the space lock, through the private manager.
     */
    public function testPagesAreCreatedAtAPlaceThroughTheIdOperations(): void
    {
        $container = self::getContainer();
        $spaces    = $container->get(WikiSpaceOperationsInterface::class);
        $pages     = $container->get(WikiPageOperationsInterface::class);
        assert($spaces instanceof WikiSpaceOperationsInterface && $pages instanceof WikiPageOperationsInterface);
        $ana   = WikiUserRef::ofId(DddUser::class, 'u-ana');
        $space = $spaces->createSpace('Docs', WikiSpaceOwnerScope::Team, 'team-a', 'docs');
        $b     = $pages->createPage($space->id, 'B', 'b', $ana);
        $a     = $pages->createPageAt($space->id, null, 0, 'A', 'a', $ana);
        $c     = $pages->createPageNextTo($b->id, true, 'C', 'c', $ana);
        $child = $pages->createPageAt($space->id, $b->id, 0, 'Child', 'x', $ana);

        $connection = $container->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);
        self::assertSame(['A', 'B', 'C'], $connection->fetchFirstColumn('SELECT title FROM wiki_pages WHERE parent_id IS NULL ORDER BY position'));
        self::assertSame($b->id, $child->parentId);
        self::assertSame([$a->id, $c->id], [$pages->findPage($a->id)?->id, $pages->findPage($c->id)?->id]);
        self::assertSame(['u-ana', 'u-ana', 'u-ana', 'u-ana'], $connection->fetchFirstColumn('SELECT author_id FROM wiki_page_revisions'));
    }

    /**
     * Spec 234 on the DDD host (1.36.0): the provenance columns come from the XML mapping, the batch provenance reads
     * the stored ids (no IDENTITY() on an id column), the badge hook is asked about `user_class` + id (never the
     * stored reference's class), and the erasure of a person's actor labels matches their id byte for byte.
     */
    public function testProvenanceBadgesAndErasureWorkWithXmlMappingAndUserIds(): void
    {
        $container = self::getContainer();
        $spaces    = $container->get(WikiSpaceOperationsInterface::class);
        $pages     = $container->get(WikiPageOperationsInterface::class);
        assert($spaces instanceof WikiSpaceOperationsInterface && $pages instanceof WikiPageOperationsInterface);
        $space = $spaces->createSpace('Docs', WikiSpaceOwnerScope::Team, 'team-a', 'docs');
        $page  = $pages->createPage($space->id, 'Home', '# Home', WikiUserRef::ofId(DddUser::class, 'u-ana'), null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $pages->saveRevision($page->id, 'Home', '# Bob', WikiUserRef::ofId(DddUser::class, 'u-bob'), WikiPageRevision::FORMAT_MARKDOWN, 1);
        $pages->saveRevision($page->id, 'Home', '# Look-alike', WikiUserRef::ofId(DddUser::class, 'U-ana'), WikiPageRevision::FORMAT_MARKDOWN, 2);

        // (a) The XML mapping carries the 1.34 columns: the host's provider recorded every write.
        $connection = $container->get('doctrine.dbal.default_connection');
        assert($connection instanceof Connection);
        $stored = static fn (): array => $connection->fetchAllNumeric('SELECT author_id, channel, actor_label FROM wiki_page_revisions ORDER BY revision_number');
        self::assertSame([['u-ana', 'api', 'Deploy token'], ['u-bob', 'api', 'Deploy token'], ['U-ana', 'api', 'Deploy token']], $stored());

        // (b) Creator and last write by stored id.
        $finder = $container->get(WikiPageProvenanceFinderInterface::class);
        assert($finder instanceof WikiPageProvenanceFinderInterface);
        self::assertEquals([$page->id => new WikiPageProvenance('u-ana', 'api', 'Deploy token', 'U-ana')], $finder->pageProvenance([$page->id, 'missing']));

        // (c) The history in a kept-alive kernel: badges asked by user_class + exact id; only Bob's row is an agent.
        DddBadges::$asked = [];
        $response         = $this->raw('/tools/wiki/spaces/docs/pages/' . $page->slug . '/history', $this->signIn('u-ana'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, substr_count((string) $response->getContent(), 'data-testid="wiki-member-badge"'), 'the look-alike U-ana is not Bob, nor Ana');
        self::assertSame([[DddUser::class . '#U-ana', DddUser::class . '#u-bob', DddUser::class . '#u-ana']], DddBadges::$asked);

        // (c) Erasure: Ana's label only (the look-alike U-ana keeps theirs), then Bob as the CLI finds a deleted user.
        $erasure = $container->get(WikiWriteProvenanceErasure::class);
        assert($erasure instanceof WikiWriteProvenanceErasure);
        $ana = new DddUser('u-ana', 'team-a');
        self::assertSame([1], array_map(static fn (array $row): int => $row['revision'], $erasure->exportActor($ana)));
        self::assertSame(1, $erasure->eraseActor($ana));
        self::assertSame(0, $erasure->eraseActor($ana), 'nothing left');
        self::assertSame(1, $erasure->eraseActor(new WikiStoredUserRef('u-bob')));
        self::assertSame([['u-ana', 'api', null], ['u-bob', 'api', null], ['U-ana', 'api', 'Deploy token']], $stored());
    }

    /**
     * 1.51 (DDD-host checklist B7, B8, B10 — DDD host leg): the read ports and the page rule by ids with XML mapping, user
     * ids, the private entity manager and the twin space policy, in a kept-alive kernel: two people in turn get their own
     * answers, the application's manager holds no wiki row, and the decisions are the guard's.
     */
    public function testTheReadPortsAndThePageRuleWorkOnTheDddHost(): void
    {
        $container = self::getContainer();
        $spaces    = $container->get(WikiSpaceOperationsInterface::class);
        $pages     = $container->get(WikiPageOperationsInterface::class);
        $query     = $container->get(WikiSpaceQueryInterface::class);
        $pageQuery = $container->get(WikiPageQueryInterface::class);
        $access    = $container->get(WikiPageAccessInterface::class);
        $guard     = $container->get(WikiSpaceGuard::class);
        assert($spaces instanceof WikiSpaceOperationsInterface && $pages instanceof WikiPageOperationsInterface && $query instanceof WikiSpaceQueryInterface
            && $pageQuery instanceof WikiPageQueryInterface && $access instanceof WikiPageAccessInterface && $guard instanceof WikiSpaceGuard);
        $ana   = WikiUserRef::ofId(DddUser::class, 'u-ana');
        $bob   = WikiUserRef::ofId(DddUser::class, 'u-bob');
        $docs  = $spaces->createSpace('Docs', WikiSpaceOwnerScope::Team, 'team-a', 'docs');
        $ops   = $spaces->createSpace('Ops', WikiSpaceOwnerScope::Team, 'team-b', 'ops');
        $home  = $pages->createPage($docs->id, 'Home', '# Home', $ana, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $guide = $pages->createPage($docs->id, 'Guide', sprintf('See [[wiki:%s]]', $home->id), $ana, $home->id, null, WikiPageRevision::FORMAT_MARKDOWN);
        $run   = $pages->createPage($ops->id, 'Runbook', sprintf('Also [[wiki:%s]]', $home->id), $bob, null, null, WikiPageRevision::FORMAT_MARKDOWN);

        self::assertSame(['Docs'], array_map(static fn (WikiSpaceSnapshot $space): string => $space->name, $query->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['team-a']))));
        self::assertSame([], $query->findSpaces(WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['Team-a'])), 'look-alike owner');
        self::assertEquals(new WikiOwnerUsage('team-a', 1, 0, 2, 0, 0), $query->usage(WikiSpaceOwnerScope::Team, ['team-a'])['team-a']);
        self::assertSame(2, $pageQuery->countPages(WikiPageCriteria::ofOwners(WikiSpaceOwnerScope::Team, ['team-a'])));
        self::assertSame(['Home'], array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pageQuery->ancestors([$guide->id])[$guide->id]));

        // Two people in turn, one kept-alive kernel: each gets their own answer (no memo between calls).
        $private = $container->get(WikiEntityManagerProvider::class);
        assert($private instanceof WikiEntityManagerProvider);
        for ($round = 0; $round < 3; ++$round) {
            self::assertSame(['Guide'], array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pageQuery->backlinks($home->id, $ana)));
            self::assertSame(['Runbook'], array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pageQuery->backlinks($home->id, $bob)), 'Bob reads his team\'s page only');
            self::assertSame(['Guide', 'Runbook'], array_map(static fn (WikiPageSnapshot $page): string => $page->title, $pageQuery->backlinks($home->id)));
            self::assertSame([$home->id, $guide->id], $access->readablePageIds($ana, [$home->id, $run->id, $guide->id]));
            self::assertSame([$run->id], $access->readablePageIds($bob, [$home->id, $run->id, $guide->id]));
            self::assertSame(['Runbook'], array_map(static fn (WikiPageSearchHit $hit): string => $hit->page->title, $pageQuery->searchPages([$docs->id, $ops->id], 'also', $bob)->hits));
            // The guard, asked with the same person as the bundle's stand-in user, decides alike.
            foreach ([$ana, $bob] as $person) {
                foreach ([$home, $guide, $run] as $page) {
                    $entity = $private->get()->find(WikiPage::class, $page->id);
                    assert($entity instanceof WikiPage);
                    self::assertSame($guard->isPageReadable(WikiUserRefs::securityUser($person), $entity->getSpace(), $entity), $access->isPageReadable($person, $page->id));
                }
            }
            $private->get()->clear();
        }

        $host = $container->get('doctrine.orm.entity_manager');
        assert($host instanceof EntityManagerInterface);
        self::assertSame([], $host->getUnitOfWork()->getIdentityMap(), 'the application\'s manager holds no wiki row');
    }

    /**
     * @return array<string, string>
     */
    private function signIn(string $id): array
    {
        $this->client->getCookieJar()->clear();
        $this->client->loginUser(new DddUser($id, DddUsers::TEAMS[$id]), 'main');
        $cookies = [];
        foreach ($this->client->getCookieJar()->all() as $cookie) {
            $cookies[$cookie->getName()] = $cookie->getValue();
        }
        self::assertNotSame([], $cookies);

        return $cookies;
    }

    /**
     * @param array<string, string> $cookies
     */
    private function raw(string $uri, array $cookies): Response
    {
        $kernel = self::getContainer()->get('http_kernel');
        assert($kernel instanceof HttpKernelInterface && $kernel instanceof TerminableInterface);
        $request  = Request::create($uri, Request::METHOD_GET, [], $cookies);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
