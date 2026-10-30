<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Nowo\WikiBundle\Command\WikiConvertMarkdownCommand;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiSpaceRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiAuthorResolver;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiRevisionDiffService;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function array_map;
use function extension_loaded;
use function preg_replace;
use function sort;

/**
 * 1.11 Markdown storage on a real schema: Markdown revisions, search on the source, Markdown diffs, restores and the
 * HTML → Markdown conversion command.
 */
final class WikiMarkdownStorageTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $user;

    private WikiSpace $space;

    private EventDispatcher $dispatcher;

    private RecordingLogger $logger;

    private WikiPageService $service;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required.');
        }

        $this->em    = SqliteWikiEntityManager::create();
        $this->user  = new TestUserEntity('u-1');
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($this->user);
        $this->em->persist($this->space);
        $this->em->flush();

        $this->dispatcher = new EventDispatcher();
        $this->logger     = new RecordingLogger();
        $this->service    = new WikiPageService(
            new DoctrineOrmWikiPageRepository($this->em),
            new DoctrineOrmWikiPageRevisionRepository($this->em),
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            $this->dispatcher,
            $this->logger,
        );
    }

    public function testMarkdownRevisionKeepsTheSourceAndASanitizedRenderCache(): void
    {
        $markdown = "# Runbook\r\n\r\n| Step | Owner |\r\n|---|---|\r\n| Restart | ops |\r\n\r\n<script>alert(1)</script>";
        $page     = $this->service->create($this->space, 'Runbook', $markdown, $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->em->clear();

        $stored = $this->em->find(WikiPage::class, $page->getId())?->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $stored);
        self::assertSame(WikiPageRevision::FORMAT_MARKDOWN, $stored->getFormat());
        self::assertTrue($stored->isMarkdown());
        self::assertSame("# Runbook\n\n| Step | Owner |\n|---|---|\n| Restart | ops |\n\n<script>alert(1)</script>", $stored->getContentMarkdown());
        self::assertStringContainsString('<table>', $stored->getContentHtml());
        self::assertStringContainsString('<td>Restart</td>', $stored->getContentHtml());
        self::assertStringNotContainsString('<script', $stored->getContentHtml());
    }

    public function testHtmlRevisionsStayHtml(): void
    {
        $page     = $this->service->create($this->space, 'Plain', '<p>Plain body</p>', $this->user);
        $revision = $page->getCurrentRevision();

        self::assertInstanceOf(WikiPageRevision::class, $revision);
        self::assertSame(WikiPageRevision::FORMAT_HTML, $revision->getFormat());
        self::assertFalse($revision->isMarkdown());
        self::assertNull($revision->getContentMarkdown());
    }

    public function testUnknownFormatIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown revision format "rtf".');

        $this->service->create($this->space, 'Odd', 'x', $this->user, null, null, 'rtf');
    }

    public function testSearchReadsTheMarkdownSourceOfMarkdownRevisions(): void
    {
        $this->service->create($this->space, 'Markdown page', 'Rotate the **keys** monthly.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->service->create($this->space, 'Html page', '<p>Rotate the <strong>keys</strong> yearly.</p>', $this->user);
        $this->em->clear();

        $search = new WikiSearchService($this->em);
        $space  = $this->em->find(WikiSpace::class, $this->space->getId());
        self::assertInstanceOf(WikiSpace::class, $space);

        self::assertSame(['Markdown page'], $this->titles($search->search($space, '**keys**')));
        self::assertSame(['Html page'], $this->titles($search->search($space, '<strong>keys')));
        self::assertSame(['Html page', 'Markdown page'], $this->titles($search->search($space, 'Rotate')));
    }

    public function testSaveDiffAndRestoreMarkdownRevisions(): void
    {
        $page   = $this->service->create($this->space, 'Guide', "Intro\n\n- one", $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $first  = $page->getCurrentRevision();
        $second = $this->service->saveRevision($page, 'Guide', "Intro\n\n- one\n- two", $this->user, WikiPageRevision::FORMAT_MARKDOWN);
        self::assertInstanceOf(WikiPageRevision::class, $first);

        $diff = (new WikiRevisionDiffService(new DoctrineOrmWikiPageRevisionRepository($this->em)))->diff($first, $second);
        self::assertSame([
            ['kind' => 'same', 'line' => 'Intro'],
            ['kind' => 'same', 'line' => '- one'],
            ['kind' => 'add', 'line' => '- two'],
        ], $diff);

        $restored = $this->service->restore($page, $first, $this->user);
        self::assertSame(3, $restored->getRevisionNumber());
        self::assertTrue($restored->isMarkdown());
        self::assertSame("Intro\n\n- one", $restored->getContentMarkdown());
        self::assertStringContainsString('<li>one</li>', $restored->getContentHtml());
    }

    public function testConvertCommandAppendsOneMarkdownRevisionPerHtmlPageAndIsIdempotent(): void
    {
        $html     = $this->service->create($this->space, 'Html', '<h2>Setup</h2><p>Use <s>old</s> new.</p><ul data-type="taskList"><li data-type="taskItem" data-checked="true"><p>done</p></li></ul>', $this->user);
        $markdown = $this->service->create($this->space, 'Md', 'Already *Markdown*.', $this->user, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $archived = $this->service->create($this->space, 'Old', '<p>Archived body</p>', $this->user);
        $this->service->archive($archived);
        $this->em->persist(new WikiPage($this->space, 'empty', 'Empty'));
        $this->em->flush();

        $saved = [];
        $this->dispatcher->addListener(WikiEvents::PAGE_SAVED, static function (WikiPageSavedEvent $event) use (&$saved): void {
            $saved[] = $event->page->getSlug();
        });

        $tester = new CommandTester($this->command());
        self::assertSame(Command::SUCCESS, $tester->execute(['spaces' => ['eng'], '--author-id' => 'u-1', '--dry-run' => true]));
        self::assertStringContainsString('Dry run: 2 converted, 1 already in Markdown, 1 without revision.', $this->display($tester));
        self::assertSame(1, $html->getCurrentRevision()?->getRevisionNumber());
        self::assertSame([], $saved);

        self::assertSame(Command::SUCCESS, $tester->execute(['spaces' => ['eng'], '--author-id' => 'u-1'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertStringContainsString('Conversion finished: 2 converted, 1 already in Markdown, 1 without revision.', $this->display($tester));
        self::assertStringContainsString('eng/html', $this->display($tester));
        self::assertSame(['html', 'old'], $saved);

        $current = $html->getCurrentRevision();
        self::assertInstanceOf(WikiPageRevision::class, $current);
        self::assertSame(2, $current->getRevisionNumber());
        self::assertSame("## Setup\n\nUse ~~old~~ new.\n\n- [x] done", $current->getContentMarkdown());
        self::assertSame('Html', $html->getTitle());
        self::assertSame(1, $markdown->getCurrentRevision()?->getRevisionNumber());
        self::assertTrue($archived->getCurrentRevision()?->isMarkdown());
        self::assertTrue($archived->isArchived());

        $history = (new DoctrineOrmWikiPageRevisionRepository($this->em))->findByPage($html);
        self::assertSame([WikiPageRevision::FORMAT_MARKDOWN, WikiPageRevision::FORMAT_HTML], array_map(static fn (WikiPageRevision $revision): string => $revision->getFormat(), $history));
        self::assertContains('info: Wiki page converted to Markdown.', $this->logger->lines());

        self::assertSame(Command::SUCCESS, $tester->execute(['spaces' => ['eng'], '--author-id' => 'u-1']));
        self::assertStringContainsString('0 converted, 3 already in Markdown', $this->display($tester));
    }

    public function testConvertCommandNeedsAnAuthorAndKnownSpaces(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(Command::FAILURE, $tester->execute(['spaces' => ['eng']]));
        self::assertStringContainsString('--author-id', $this->display($tester));

        self::assertSame(Command::FAILURE, $tester->execute(['spaces' => ['eng', 'missing'], '--author-id' => 'u-1']));
        self::assertStringContainsString('Space "missing" not found.', $this->display($tester));
    }

    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    private function command(): WikiConvertMarkdownCommand
    {
        return new WikiConvertMarkdownCommand(
            new DoctrineOrmWikiSpaceRepository($this->em),
            new DoctrineOrmWikiPageRepository($this->em),
            new WikiMarkdownConverter(),
            $this->service,
            new WikiAuthorResolver($this->em, TestUserEntity::class),
        );
    }

    /**
     * @param list<array{page: WikiPage, space: WikiSpace, excerpt: string}> $results
     *
     * @return list<string>
     */
    private function titles(array $results): array
    {
        $titles = array_map(static fn (array $row): string => $row['page']->getTitle(), $results);
        sort($titles);

        return $titles;
    }
}
