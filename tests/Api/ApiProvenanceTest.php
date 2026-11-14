<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;

use function array_column;
use function array_filter;
use function count;
use function str_contains;
use function substr;

/**
 * Spec 234: the API answers carry the write provenance — pages `created_by`, `updated_by`, `channel`,
 * `token_name`; revisions `channel`, `token_name` — as plain JSON values (no HTML), null when not recorded, and the
 * label never once its author is deleted. Lists stay within a fixed number of queries.
 */
final class ApiProvenanceTest extends ApiTestCase
{
    private const SCRIPT = '<script>alert(1)</script> MCP token';

    public function testWithoutAProviderTheFieldsAreNull(): void
    {
        $page = $this->page($this->eng, 'Guide', 'first');

        $head = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()));
        self::assertSame(['created_by' => $head['updated_by'], 'channel' => null, 'token_name' => null], ['created_by' => $head['created_by'], 'channel' => $head['channel'], 'token_name' => $head['token_name']]);
        self::assertSame('api-u-ana', $head['created_by']['id']);

        $row = $this->json($this->call('GET', '/wikis/eng/pages'))['pages'][0];
        self::assertSame([$row['updated_by'], null, null], [$row['created_by'], $row['channel'], $row['token_name']]);
        $revision = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions'))['revisions'][0];
        self::assertSame([null, null], [$revision['channel'], $revision['token_name']]);
    }

    public function testPagesAndRevisionsCarryTheProvenanceAsPlainJson(): void
    {
        $this->token('bob', 'u-bob', WikiApiLevel::Read);
        $page = $this->twoRevisions();

        $response = $this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'bob');
        self::assertStringNotContainsString('<script>', (string) $response->getContent(), 'json_encode escapes the markup');
        $head = $this->json($response);
        self::assertSame('Editor', substr((string) $head['created_by']['name'], 0, 6));
        self::assertNotSame($head['created_by']['name'], $head['updated_by']['name'], 'created by Ana, updated by Bob');
        self::assertSame('api-u-bob', $head['updated_by']['id']);
        self::assertNull($head['created_by']['id'], 'an id only for the token\'s own user');
        self::assertSame(['mcp', self::SCRIPT], [$head['channel'], $head['token_name']]);

        $row = $this->json($this->call('GET', '/wikis/eng/pages', 'bob'))['pages'][0];
        self::assertSame([$head['created_by'], $head['updated_by'], 'mcp', self::SCRIPT], [$row['created_by'], $row['updated_by'], $row['channel'], $row['token_name']]);

        $revisions = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions', 'bob'))['revisions'];
        self::assertSame(['mcp', 'web'], array_column($revisions, 'channel'));
        self::assertSame([self::SCRIPT, null], array_column($revisions, 'token_name'));
        $one = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions/2', 'bob'));
        self::assertSame(['mcp', self::SCRIPT], [$one['channel'], $one['token_name']]);
    }

    /**
     * Audit PQ-M1: with history.show_names off (the default) other people's writes answer an opaque "Editor xxxx" and
     * no token name, which would link and name them; the author still gets their own.
     */
    public function testAnotherPersonsTokenNameIsNotAnsweredBesideAnOpaqueName(): void
    {
        $this->token('bob', 'u-bob', WikiApiLevel::Read);
        $page = $this->twoRevisions();

        $head = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()));
        self::assertSame(['mcp', null], [$head['channel'], $head['token_name']], 'Ana reads Bob\'s write');
        self::assertStringStartsWith('Editor ', (string) $head['updated_by']['name']);
        $row = $this->json($this->call('GET', '/wikis/eng/pages'))['pages'][0];
        self::assertSame(['mcp', null], [$row['channel'], $row['token_name']]);
        $revisions = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions'))['revisions'];
        self::assertSame([null, null], array_column($revisions, 'token_name'));
        self::assertSame([null, null], [$this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions/2'))['token_name'], null]);

        self::assertSame(self::SCRIPT, $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'bob'))['token_name'], 'Bob gets his own');
    }

    /**
     * Audit PQ-D1-L1 (JX-W1): a back-office admin of another identity domain whose id collides with Bob's is not Bob:
     * never Bob's token name.
     */
    public function testAForeignUserWithTheSameIdDoesNotGetTheTokenName(): void
    {
        $page                                 = $this->twoRevisions();
        ApiTestHost::$tokens['foreign']       = ['user' => 'u-bob', 'level' => WikiApiLevel::Read, 'where' => WikiApiWhere::everything(), 'foreign' => true];
        ApiTestHost::$grants['foreign-u-bob'] = ['eng' => [WikiAction::View, WikiAction::History]];

        $head = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId(), 'foreign'));
        self::assertSame(['mcp', null], [$head['channel'], $head['token_name']]);
        self::assertNull($this->json($this->call('GET', '/wikis/eng/pages', 'foreign'))['pages'][0]['token_name']);
        self::assertSame([null, null], array_column($this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions', 'foreign'))['revisions'], 'token_name'));
        self::assertNull($this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions/2', 'foreign'))['token_name']);
    }

    /**
     * Audit PQ-D1-I1: a save between the list's metadata read and its provenance read must not pair the new write's
     * token name with the previous author (Ana reads; Bob saves meanwhile).
     */
    public function testAWriteBetweenTheTwoListReadsDoesNotLeakItsTokenName(): void
    {
        $page = $this->page($this->eng, 'Guide', 'first');
        $this->em()->clear();
        ApiTestHost::$raceOnView = function () use ($page): void {
            $em = $this->em();
            $this->pages()->saveRevision($em->find(WikiPage::class, $page->getId()) ?? $page, 'Guide', 'by bob', $em->find(TestUserEntity::class, 'u-bob') ?? $this->bob, WikiPageRevision::FORMAT_MARKDOWN);
            $em->getConnection()->executeStatement("UPDATE wiki_page_revisions SET channel = 'mcp', actor_label = 'Bob token' WHERE revision_number = 2");
        };

        $row = $this->json($this->call('GET', '/wikis/eng/pages'))['pages'][0];
        self::assertNull(ApiTestHost::$raceOnView, 'the race ran');
        self::assertSame(1, $row['revision'], 'the list read the page before Bob\'s save');
        self::assertNull($row['token_name']);
    }

    /** Nothing is kept about a former person: the token name of their writes is no longer answered. */
    public function testTheTokenNameOfADeletedAuthorIsNotAnswered(): void
    {
        $page = $this->twoRevisions();
        $this->em()->getConnection()->executeStatement("UPDATE wiki_page_revisions SET author_id = NULL WHERE author_id = 'u-bob'");
        $this->em()->clear();

        $head = $this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId()));
        self::assertSame([null, 'mcp', null], [$head['updated_by'], $head['channel'], $head['token_name']]);
        self::assertSame('api-u-ana', $head['created_by']['id']);
        $row = $this->json($this->call('GET', '/wikis/eng/pages'))['pages'][0];
        self::assertSame([null, 'mcp', null], [$row['updated_by'], $row['channel'], $row['token_name']]);
        self::assertSame([null, null], array_column($this->json($this->call('GET', '/wikis/eng/pages/' . $page->getId() . '/revisions'))['revisions'], 'token_name'));
    }

    /** No N+1: creators and provenance of a list are read in a fixed number of queries. */
    public function testTheListStaysWithinTheSameQueryBudget(): void
    {
        $this->token('bob', 'u-bob', WikiApiLevel::Read);
        for ($i = 0; $i < 5; ++$i) {
            $this->twoRevisions('Small ' . $i);
        }
        $small = $this->queries();
        for ($i = 0; $i < 30; ++$i) {
            $this->twoRevisions('Large ' . $i);
        }

        self::assertSame($small, $this->queries());
    }

    private function twoRevisions(string $title = 'Guide'): WikiPage
    {
        $page = $this->page($this->eng, $title, 'first');
        $em   = $this->em();
        $this->pages()->saveRevision($em->find(WikiPage::class, $page->getId()) ?? $page, $title, "first\nsecond", $em->find(TestUserEntity::class, 'u-bob') ?? $this->bob, WikiPageRevision::FORMAT_MARKDOWN);
        // What a host provider records (the API kernel has none): Ana's first save from the web, Bob's from MCP.
        $connection = $em->getConnection();
        $connection->executeStatement("UPDATE wiki_page_revisions SET channel = 'web' WHERE page_id = ? AND revision_number = 1", [$page->getId()]);
        $connection->executeStatement("UPDATE wiki_page_revisions SET channel = 'mcp', actor_label = ? WHERE page_id = ? AND revision_number = 2", [self::SCRIPT, $page->getId()]);
        $em->clear();

        return $page;
    }

    private function queries(): int
    {
        $logger = static::getContainer()->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);
        $this->em()->clear();
        $logger->records = [];
        self::assertSame(200, $this->call('GET', '/wikis/eng/pages', 'bob')->getStatusCode());

        return count(array_filter($logger->records, static fn (array $record): bool => str_contains($record['message'], 'Executing')));
    }
}
