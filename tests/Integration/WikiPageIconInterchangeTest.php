<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Doctrine\ORM\EntityManager;
use FilesystemIterator;
use Nowo\WikiBundle\Dto\WikiImportReport;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageIconService;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

use function file_get_contents;
use function file_put_contents;
use function sys_get_temp_dir;
use function uniqid;

/**
 * 1.38: the page icon through export and import — `wiki_icon` in the front matter of a page that has one (other pages
 * export as before), set again on import (create and update), an icon that is not allowed left out with a message.
 */
final class WikiPageIconInterchangeTest extends TestCase
{
    private EntityManager $em;

    private TestUserEntity $author;

    private string $dir;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->em     = WikiTestDatabase::create();
        $this->author = new TestUserEntity('u-io');
        $this->em->persist($this->author);
        $this->em->flush();
        $this->dispatcher = new EventDispatcher();
        $this->dir        = sys_get_temp_dir() . '/wiki-icon-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dir);
        $this->em->getConnection()->close();
    }

    public function testExportThenImportKeepsTheIcons(): void
    {
        $source = $this->space('src');
        $launch = $this->pages()->create($source, 'Launch', '# Launch', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->pages()->create($source, 'Plain', 'Text', $this->author, $launch, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->icons()->setIcon($launch, 'tabler:rocket');

        $this->exporter()->export($source, $this->dir, WikiInterchangeFormat::Outline);
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'md') {
                $files[$file->getFilename()] = (string) file_get_contents($file->getPathname());
            }
        }
        self::assertStringContainsString('wiki_icon: \'tabler:rocket\'', $files['launch.md'] ?? $files['Launch.md'] ?? implode("\n", $files));
        self::assertSame(1, substr_count(implode("\n", $files), 'wiki_icon'), 'Only the page with an icon carries it.');

        $target = $this->space('dst');
        $report = $this->importer()->import($target, $this->dir, WikiInterchangeFormat::Auto, $this->author);
        self::assertSame(2, $report->created);
        self::assertSame('tabler:rocket', $this->icon($target, 'launch'));
        self::assertNull($this->icon($target, 'plain'));
    }

    public function testImportUpdatesKeepsClearsAndRefusesIcons(): void
    {
        $space = $this->space('eng');
        $page  = $this->pages()->create($space, 'Runbook', 'one', $this->author, null, null, WikiPageRevision::FORMAT_MARKDOWN);
        $this->icons()->setIcon($page, 'tabler:tool');

        // No wiki_icon: kept.
        $this->import($space, "---\ntitle: Runbook\nwiki_slug: runbook\n---\n\ntwo\n");
        self::assertSame('tabler:tool', $this->icon($space, 'runbook'));
        // Another one: replaced.
        $this->import($space, "---\ntitle: Runbook\nwiki_slug: runbook\nwiki_icon: 'tabler:book'\n---\n\nthree\n");
        self::assertSame('tabler:book', $this->icon($space, 'runbook'));
        // Not allowed: left out with a message, the page is still updated.
        $report = $this->import($space, "---\ntitle: Runbook\nwiki_slug: runbook\nwiki_icon: '<img src=x onerror=alert(1)>'\n---\n\nfour\n");
        self::assertSame(1, $report->updated);
        self::assertSame('tabler:book', $this->icon($space, 'runbook'));
        self::assertContains('Ignored the icon of page "runbook": not an allowed icon name.', $report->messages);
        $report = $this->import($space, "---\ntitle: Runbook\nwiki_slug: runbook\nwiki_icon: [1, 2]\n---\n\nfive\n");
        self::assertContains('Ignored the icon of page "runbook": not an allowed icon name.', $report->messages);
        // Audit QY-L2: the page archived right after its revision committed: imported without the icon, with a message.
        $this->dispatcher->addListener(WikiEvents::PAGE_SAVED, $archive = fn (WikiPageSavedEvent $event): int|string => $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET archived_at = ? WHERE id = ?', ['2026-11-16 10:00:00', $event->page->getId()]));
        $report = $this->import($space, "---\ntitle: Runbook\nwiki_slug: runbook\nwiki_icon: 'tabler:rocket'\n---\n\nfive b\n");
        $this->dispatcher->removeListener(WikiEvents::PAGE_SAVED, $archive);
        self::assertSame(1, $report->updated);
        self::assertContains('Could not save the icon of page "runbook"; the page was imported without it.', $report->messages);
        self::assertSame('tabler:book', $this->icon($space, 'runbook'));
        $this->em->getConnection()->executeStatement('UPDATE wiki_pages SET archived_at = NULL');
        // Empty: cleared.
        $this->import($space, "---\ntitle: Runbook\nwiki_slug: runbook\nwiki_icon: ''\n---\n\nsix\n");
        self::assertNull($this->icon($space, 'runbook'));
    }

    private function import(WikiSpace $space, string $document): WikiImportReport
    {
        (new Filesystem())->remove($this->dir);
        (new Filesystem())->mkdir($this->dir);
        file_put_contents($this->dir . '/runbook.md', $document);

        return $this->importer()->import($space, $this->dir, WikiInterchangeFormat::Auto, $this->author, true);
    }

    private function space(string $slug): WikiSpace
    {
        $space = new WikiSpace($slug, $slug, WikiSpaceOwnerScope::Team, 'team-1');
        $this->em->persist($space);
        $this->em->flush();

        return $space;
    }

    private function icon(WikiSpace $space, string $slug): mixed
    {
        $page = $this->em->getRepository(WikiPage::class)->findOneBy(['space' => $space, 'slug' => $slug]);
        self::assertInstanceOf(WikiPage::class, $page);
        $this->em->refresh($page);

        return $page->getIcon();
    }

    private function pages(): WikiPageService
    {
        return new WikiPageService(new DoctrineOrmWikiPageRepository($this->em), new DoctrineOrmWikiPageRevisionRepository($this->em), new WikiSlugger(), new WikiHtmlSanitizer(), $this->dispatcher, runner: new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0));
    }

    private function icons(): WikiPageIconService
    {
        return new WikiPageIconService(new DoctrineOrmWikiPageRepository($this->em), ['tabler'], new WikiTransactionRunner($this->em, backoffBaseMilliseconds: 0));
    }

    private function exporter(): WikiDocumentExporter
    {
        return new WikiDocumentExporter(new DoctrineOrmWikiPageRepository($this->em), new WikiPageTreeBuilder(), new WikiMarkdownConverter(), new WikiFrontMatterParser());
    }

    private function importer(): WikiDocumentImporter
    {
        $parser = new WikiFrontMatterParser();

        return new WikiDocumentImporter(new WikiArchiveHelper(), new WikiFormatDetector($parser), new WikiDocumentTreeReader($parser), new WikiMarkdownConverter(), new DoctrineOrmWikiPageRepository($this->em), $this->pages(), new WikiSlugger(), null, 'markdown', $this->icons());
    }
}
