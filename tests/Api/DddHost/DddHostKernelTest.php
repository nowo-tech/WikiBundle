<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Link\WikiPageLinkOperationsInterface;
use Nowo\WikiBundle\Service\WikiPageOperationsInterface;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiSpaceOperationsInterface;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use InvalidArgumentException;
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

/**
 * 1.33.0 (REQ-ARCH-001, "both host kinds are tested"): the DDD host path end to end. The standard host path is the rest of
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
        $this->client = static::createClient();
        $this->client->disableReboot();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        assert($em instanceof EntityManagerInterface);
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        DddPolicies::$asked = [];
    }

    public function testTheHostMapsNothingOfItsOwnAndTheWikiComesFromXmlWithUserIds(): void
    {
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        assert($em instanceof EntityManagerInterface);
        $classes = array_map(static fn ($metadata): string => $metadata->getName(), $em->getMetadataFactory()->getAllMetadata());
        foreach ($classes as $class) {
            self::assertStringStartsWith('Nowo\\WikiBundle\\Entity\\', $class, 'only wiki entities are mapped');
        }
        $driver = $em->getConfiguration()->getMetadataDriverImpl();
        self::assertInstanceOf(MappingDriverChain::class, $driver);
        $wikiDriver = null;
        foreach ($driver->getDrivers() as $namespace => $candidate) {
            if ($namespace === 'Nowo\\WikiBundle\\Entity') {
                $wikiDriver = $candidate;
            }
        }
        self::assertInstanceOf(SimplifiedXmlDriver::class, $wikiDriver);

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
        $container = static::getContainer();
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
        $container = static::getContainer();
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
        $kernel = static::getContainer()->get('http_kernel');
        assert($kernel instanceof HttpKernelInterface && $kernel instanceof TerminableInterface);
        $request  = Request::create($uri, Request::METHOD_GET, [], $cookies);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
