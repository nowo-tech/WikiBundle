<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiActorLabels;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\Provenance\WikiWriteProvenance;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiRevisionLabelRepository;
use Nowo\WikiBundle\Repository\WikiPageProvenance;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingBadgeProvider;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function array_map;
use function array_values;

use const DATE_ATOM;

/**
 * Spec 234: the provenance columns on SQLite — stored per revision, read in batch (creator and last write),
 * shown in the history timeline with the host's badges, exported and erased (personal data).
 */
final class WikiProvenanceTest extends TestCase
{
    private EntityManager $em;

    private WikiSpace $space;

    private TestUserEntity $ana;

    private TestUserEntity $bot;

    protected function setUp(): void
    {
        $this->em    = SqliteWikiEntityManager::create();
        $this->space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $this->ana   = new TestUserEntity('ana');
        $this->bot   = new TestUserEntity('bot');
        foreach ([$this->space, $this->ana, $this->bot] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function testTheColumnsRoundTrip(): void
    {
        $page = $this->page('run');
        $this->revision($page, 1, $this->bot, new WikiWriteProvenance('api', 'Deploy token <b>'));
        $this->em->clear();

        $stored = $this->em->createQueryBuilder()->select('r')->from(WikiPageRevision::class, 'r')->getQuery()->getSingleResult();
        self::assertInstanceOf(WikiPageRevision::class, $stored);
        self::assertSame('api', $stored->getChannel());
        self::assertSame('Deploy token <b>', $stored->getActorLabel());
    }

    public function testThePagesProvenanceIsReadInTwoQueriesWhateverTheirNumber(): void
    {
        $repository = new DoctrineOrmWikiPageRevisionRepository($this->provider());
        self::assertSame([], $repository->pageProvenance([]));

        $run = $this->page('run');
        $this->revision($run, 1, $this->ana, null);
        $this->revision($run, 2, $this->ana, null);
        $current = $this->revision($run, 3, $this->bot, new WikiWriteProvenance('mcp', 'Claude'));
        $this->current($run, $current);
        // History thinning removed the creator's first save: the oldest kept revision is still the creator's.
        $this->em->getConnection()->executeStatement('DELETE FROM wiki_page_revisions WHERE revision_number = 1');
        $empty = $this->page('empty');

        $provenance = $repository->pageProvenance([$run->getId(), $empty->getId(), 'missing']);
        self::assertEquals([$run->getId() => new WikiPageProvenance('ana', 'mcp', 'Claude', 'bot')], $provenance);
    }

    public function testTheTimelineCarriesBadgesAndProvenanceButNothingOfADeletedAuthor(): void
    {
        $page     = $this->page('run');
        $one      = $this->revision($page, 1, $this->ana, new WikiWriteProvenance('web', 'Ana in the browser'), '-3 hours');
        $two      = $this->revision($page, 2, $this->bot, new WikiWriteProvenance('mcp', '<script>alert(1)</script>'), '-2 hours');
        $provider = new RecordingBadgeProvider(['bot' => [new WikiMemberBadge('Agent', 'agent')]]);
        $authors  = new WikiAuthorLabel('secret');
        $timeline = new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), $authors, 30, new WikiMemberBadges($provider), new WikiActorLabels($authors, false));

        // The bot reads the history: its own token name is shown, nobody else's.
        $rows = $timeline->rows($page, [$two, $one], $this->bot);
        self::assertSame([['bot', 'ana']], $provider->calls, 'one provider call for the page of rows');
        self::assertSame('Agent', $rows[2]['badges'][0]->label);
        self::assertSame([], $rows[1]['badges']);
        self::assertSame(['mcp', 'web'], [$rows[2]['channel'], $rows[1]['channel']]);
        self::assertSame('<script>alert(1)</script>', $rows[2]['actor_label']);
        self::assertNull($rows[1]['actor_label'], 'Ana is an opaque "Editor xxxx" to the bot: no label');

        // Ana's account is deleted (ON DELETE SET NULL): the row keeps the channel, never her label or a badge.
        $this->em->getConnection()->executeStatement("UPDATE wiki_page_revisions SET author_id = NULL WHERE author_id = 'ana'");
        $this->em->clear();
        $revisions = $this->revisions($page);
        $rows      = (new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), $authors, 30, new WikiMemberBadges($provider), new WikiActorLabels($authors, true)))->rows($page, $revisions, $this->ana);
        self::assertTrue($rows[1]['deleted']);
        self::assertNull($rows[1]['actor_label']);
        self::assertSame('web', $rows[1]['channel']);
        self::assertSame([], $rows[1]['badges']);

        // Without the hook: no badge key content, provenance still read.
        $plain = (new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), new WikiAuthorLabel('secret')))->rows($page, $revisions);
        self::assertSame([], $plain[2]['badges']);
    }

    /**
     * Audit PQ-M1: with history.show_names off the "Editor xxxx" of two pages differ, and the actor label must not link
     * them (nor name the person); beside a real name a safe label is shown, an e-mail-like one never.
     */
    public function testTheActorLabelNeverLinksAnOpaqueAuthorAcrossPages(): void
    {
        $label = 'Ana Garcia laptop (ana@corp.example)';
        $rows  = [];
        foreach (['a', 'b'] as $slug) {
            $page     = $this->page($slug);
            $revision = $this->revision($page, 1, $this->ana, new WikiWriteProvenance('mcp', $label));
            $authors  = new WikiAuthorLabel('secret', false);
            $rows[]   = (new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), $authors, 30, null, new WikiActorLabels($authors, false)))->rows($page, [$revision], $this->bot)[1];
        }
        self::assertNotSame($rows[0]['author'], $rows[1]['author']);
        self::assertSame([null, null], [$rows[0]['actor_label'], $rows[1]['actor_label']]);
        self::assertSame(['mcp', 'mcp'], [$rows[0]['channel'], $rows[1]['channel']], 'the channel is categorical and stays');

        // Without the policy (a host-built timeline) no label is shown either.
        $page = $this->page('c');
        $rev  = $this->revision($page, 1, $this->ana, new WikiWriteProvenance('mcp', 'Release token'));
        self::assertNull((new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), new WikiAuthorLabel('secret')))->rows($page, [$rev])[1]['actor_label']);

        // Beside a real name (show_names on, a safe display name): a safe label is shown, an e-mail-like one never.
        $named = new WikiAuthorLabel('secret', true);
        $ana   = $this->em->find(TestUserEntity::class, 'ana');
        self::assertInstanceOf(TestUserEntity::class, $ana);
        $ana->withDisplayName('Maria Ruiz');
        $timeline = new WikiHistoryTimeline(new DoctrineOrmWikiRevisionLabelRepository($this->em), $named, 30, null, new WikiActorLabels($named, true));
        $shown    = $timeline->rows($page, [$rev], $this->bot)[1];
        self::assertTrue($shown['named']);
        self::assertSame('Release token', $shown['actor_label']);
        $mail = $this->revision($page, 2, $this->ana, new WikiWriteProvenance('mcp', $label));
        self::assertNull($timeline->rows($page, [$mail], $this->bot)[2]['actor_label']);
    }

    public function testAChannelOutsideTheAllowListIsNotTrustedOnRead(): void
    {
        $page    = $this->page('run');
        $current = $this->revision($page, 1, $this->ana, new WikiWriteProvenance('web', 'Label'));
        $this->current($page, $current);
        $this->em->getConnection()->executeStatement("UPDATE wiki_page_revisions SET channel = 'x<b>'");
        $this->em->clear();

        $stored = $this->em->createQueryBuilder()->select('r')->from(WikiPageRevision::class, 'r')->getQuery()->getSingleResult();
        self::assertInstanceOf(WikiPageRevision::class, $stored);
        self::assertNull($stored->getChannel());
        self::assertNull((new DoctrineOrmWikiPageRevisionRepository($this->em))->pageProvenance([$page->getId()])[$page->getId()]->channel);
    }

    public function testTheActorLabelsAreExportedAndErased(): void
    {
        $page = $this->page('run');
        $this->revision($page, 1, $this->ana, new WikiWriteProvenance('api', 'Ana CI token'), '-2 hours');
        $this->revision($page, 2, $this->ana, new WikiWriteProvenance('web'), '-1 hour');
        $this->revision($page, 3, $this->bot, new WikiWriteProvenance('mcp', 'Bot token'));
        $erasure = new WikiWriteProvenanceErasure($this->provider());

        $export = $erasure->exportActor($this->ana);
        self::assertCount(1, $export);
        self::assertSame(['page_id' => $page->getId(), 'revision' => 1, 'channel' => 'api', 'actor_label' => 'Ana CI token'], ['page_id' => $export[0]['page_id'], 'revision' => $export[0]['revision'], 'channel' => $export[0]['channel'], 'actor_label' => $export[0]['actor_label']]);
        self::assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, $export[0]['created_at']));

        self::assertSame(1, $erasure->eraseActor($this->ana));
        self::assertSame(0, $erasure->eraseActor($this->ana), 'nothing left');
        self::assertSame([], $erasure->exportActor($this->ana));
        self::assertSame(['api', 'web', 'mcp'], $this->column('channel'), 'the channel is not personal data');
        self::assertSame([null, null, 'Bot token'], $this->column('actor_label'));

        // The bot account is deleted before its labels were erased: the orphans are cleared in one statement.
        $this->em->getConnection()->executeStatement("UPDATE wiki_page_revisions SET author_id = NULL WHERE author_id = 'bot'");
        self::assertSame(1, $erasure->eraseOrphaned());
        self::assertSame([null, null, null], $this->column('actor_label'));
    }

    private function page(string $slug): WikiPage
    {
        $page = new WikiPage($this->em->find(WikiSpace::class, $this->space->getId()) ?? $this->space, $slug, 'T ' . $slug);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    private function revision(WikiPage $page, int $number, TestUserEntity $author, ?WikiWriteProvenance $provenance, string $ago = 'now'): WikiPageRevision
    {
        $revision = new WikiPageRevision($this->em->find(WikiPage::class, $page->getId()) ?? $page, $number, '<p>v' . $number . '</p>', $this->em->find(TestUserEntity::class, $author->getId()) ?? $author);
        (new ReflectionProperty(WikiPageRevision::class, 'createdAt'))->setValue($revision, new DateTimeImmutable($ago));
        if ($provenance instanceof WikiWriteProvenance) {
            $revision->recordProvenance($provenance);
        }
        $this->em->persist($revision);
        $this->em->flush();

        return $revision;
    }

    private function current(WikiPage $page, WikiPageRevision $revision): void
    {
        $page->setCurrentRevision($revision);
        $this->em->flush();
    }

    /**
     * @return list<WikiPageRevision>
     */
    private function revisions(WikiPage $page): array
    {
        $page = $this->em->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $page);

        return (new DoctrineOrmWikiPageRevisionRepository($this->em))->findByPage($page);
    }

    /**
     * @return list<string|null>
     */
    private function column(string $column): array
    {
        /** @var list<array<string, string|null>> $rows */
        $rows = $this->em->getConnection()->fetchAllAssociative('SELECT ' . $column . ' FROM wiki_page_revisions ORDER BY revision_number');

        return array_values(array_map(static fn (array $row): ?string => $row[$column], $rows));
    }

    private function provider(): WikiEntityManagerProvider
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManager')->willReturn($this->em);

        return new WikiEntityManagerProvider($registry);
    }
}
