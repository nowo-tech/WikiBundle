<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiExportIndexWriter;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Interchange\WikiMarkdownPortableLinks;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_diff;
use function array_values;

final class WikiDocumentExporterTest extends TestCase
{
    public function testExportsOutlineMarkdownFiles(): void
    {
        $space  = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $parent = new WikiPage($space, 'parent', 'Parent');
        $parent->setCurrentRevision(new WikiPageRevision($parent, 1, '<p>Parent</p>', new TestUser()));
        $child = new WikiPage($space, 'child', 'Child', $parent);
        $child->setCurrentRevision(new WikiPageRevision($child, 1, '<p>Child</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$parent, $child]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);

        try {
            $report = (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target, WikiInterchangeFormat::Outline);

            self::assertSame(2, $report->pagesExported);
            self::assertFileExists($target . '/parent.md');
            self::assertFileExists($target . '/child.md');
            $content = (string) file_get_contents($target . '/child.md');
            self::assertStringContainsString('wiki_parent: parent', $content);
            // 1.15.1: README.md index of exactly the exported pages, nested like the tree.
            self::assertSame([
                ['title' => 'Parent', 'path' => 'parent.md', 'depth' => 0],
                ['title' => 'Child', 'path' => 'child.md', 'depth' => 1],
            ], $report->pages);
            self::assertStringContainsString("- [Parent](parent.md)\n  - [Child](child.md)\n", (string) file_get_contents($target . '/README.md'));
        } finally {
            unlink($target . '/parent.md');
            unlink($target . '/child.md');
            $this->removeTree($target);
        }
    }

    public function testMarkdownRevisionsExportTheirSource(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'runbook', 'Runbook');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>cache</p>', new TestUser(), "Source with [[wiki:0a1b2c3d-1111-2222-3333-444455556666]]\n\n| A |\n|---|\n| 1 |"));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);

        try {
            (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target, WikiInterchangeFormat::Outline);

            $content = (string) file_get_contents($target . '/runbook.md');
            self::assertStringContainsString("Source with [[wiki:0a1b2c3d-1111-2222-3333-444455556666]]\n\n| A |\n|---|\n| 1 |", $content);
            self::assertStringNotContainsString('cache', $content);
        } finally {
            unlink($target . '/runbook.md');
            $this->removeTree($target);
        }
    }

    public function testDialectExportsLinkExportedPagesAsFiles(): void
    {
        $space  = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $guide  = new WikiPage($space, 'guide', 'Guide [v2]');
        $setup  = new WikiPage($space, 'setup', 'Set up', $guide);
        $hidden = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee';
        $guide->setCurrentRevision(new WikiPageRevision($guide, 1, '<p>c</p>', new TestUser(), 'Go to [[wiki:' . $setup->getId() . '#step-1]], not [[wiki:' . $hidden . ']]; `[[wiki:' . $setup->getId() . ']]`'));
        $setup->setCurrentRevision(new WikiPageRevision($setup, 1, '<p>Back to [[wiki:' . $guide->getId() . ']]</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$guide, $setup]);
        $exporter = new WikiDocumentExporter($pageRepository, new WikiPageTreeBuilder(), new WikiMarkdownConverter(dialect: true), new WikiFrontMatterParser());

        $outline = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        $notion  = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($outline);
        mkdir($notion);

        try {
            $exporter->export($space, $outline, WikiInterchangeFormat::Outline);
            $content = (string) file_get_contents($outline . '/guide.md');
            self::assertStringContainsString('Go to [Set up](setup.md#step-1), not [[wiki:' . $hidden . ']]; `[[wiki:' . $setup->getId() . ']]`', $content);
            self::assertStringContainsString('Back to [Guide \\[v2\\]](guide.md)', (string) file_get_contents($outline . '/setup.md'));

            $exporter->export($space, $notion, WikiInterchangeFormat::Notion);
            self::assertStringContainsString('Go to [Set up](Set%20up/Set%20up.md#step-1)', (string) file_get_contents($notion . '/Guide [v2]/Guide [v2].md'));
            self::assertStringContainsString('Back to [Guide \\[v2\\]](../Guide%20%5Bv2%5D.md)', (string) file_get_contents($notion . '/Guide [v2]/Set up/Set up.md'));
        } finally {
            $this->removeTree($outline);
            $this->removeTree($notion);
        }
    }

    public function testPortableLinksHelpers(): void
    {
        self::assertSame('no links', WikiMarkdownPortableLinks::rewrite('no links', '/x', ['a' => ['path' => '/x/a.md', 'title' => 'A']]));
        self::assertSame('[[wiki:x]]', WikiMarkdownPortableLinks::rewrite('[[wiki:x]]', '/x', []));
        $id = '0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10';
        self::assertSame(
            "a\x000\x00 b\x009\x00 [A](a.md) `[[wiki:{$id}]]`",
            WikiMarkdownPortableLinks::rewrite("a\x000\x00 b\x009\x00 [[wiki:{$id}]] `[[wiki:{$id}]]`", '/x', [$id => ['path' => '/x/a.md', 'title' => 'A']]),
            'CV-L3: NUL bytes in stored Markdown are plain text',
        );
        self::assertSame('../../b/c%20d.md', WikiMarkdownPortableLinks::relative('/r/a/e', '/r/b/c d.md'));
        self::assertSame('c.md', WikiMarkdownPortableLinks::relative('C:\\r', 'C:\\r\\c.md'));
    }

    public function testExportsNotionNestedFolders(): void
    {
        $space  = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $parent = new WikiPage($space, 'parent', 'Parent');
        $parent->setCurrentRevision(new WikiPageRevision($parent, 1, '<p>Parent</p>', new TestUser()));
        $child = new WikiPage($space, 'child', 'Child', $parent);
        $child->setCurrentRevision(new WikiPageRevision($child, 1, '<p>Child</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$parent, $child]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);

        try {
            $report = (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target, WikiInterchangeFormat::Notion);

            self::assertSame(2, $report->pagesExported);
            self::assertDirectoryExists($target . '/Parent');
            self::assertFileExists($target . '/Parent/Child/Child.md');
        } finally {
            $this->removeTree($target);
        }
    }

    public function testExportsNotionSinglePage(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro: Guide');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);

        try {
            $report = (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target, WikiInterchangeFormat::Notion);

            self::assertSame(1, $report->pagesExported);
            self::assertFileExists($target . '/Intro- Guide/Intro- Guide.md');
        } finally {
            $this->removeTree($target);
        }
    }

    public function testAutoFormatUsesOutline(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));

        try {
            $report = (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target, WikiInterchangeFormat::Auto);

            self::assertSame(1, $report->pagesExported);
            self::assertFileExists($target . '/intro.md');
        } finally {
            if (is_file($target . '/intro.md')) {
                unlink($target . '/intro.md');
            }
            $this->removeTree($target);
        }
    }

    public function testExportsPageWithoutRevision(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'draft', 'Draft');

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);

        try {
            (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target, WikiInterchangeFormat::Outline);

            self::assertFileExists($target . '/draft.md');
        } finally {
            unlink($target . '/draft.md');
            $this->removeTree($target);
        }
    }

    public function testLogsExportStartFinishAndFailure(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $logger   = new RecordingLogger();
        $exporter = new WikiDocumentExporter(
            $pageRepository,
            new WikiPageTreeBuilder(),
            new WikiMarkdownConverter(),
            new WikiFrontMatterParser(),
            $logger,
        );

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);
        try {
            $exporter->export($space, $target, WikiInterchangeFormat::Notion);
        } finally {
            $this->removeTree($target);
        }

        self::assertSame(['info: Wiki export started.', 'info: Wiki export finished.'], $logger->lines());
        self::assertSame(['bundle' => 'nowo_wiki', 'action' => 'export', 'space' => 'docs', 'format' => 'notion', 'pages_exported' => 1], $logger->records[1]['context']);

        $blocked = sys_get_temp_dir() . '/wiki-export-block-' . bin2hex(random_bytes(4));
        file_put_contents($blocked, 'blocked');
        try {
            @$exporter->export($space, $blocked . '/nested', WikiInterchangeFormat::Outline);
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
        } finally {
            unlink($blocked);
        }

        self::assertSame('error: Wiki export failed.', $logger->lines()[3]);
    }

    public function testThrowsWhenExportDirectoryCannotBeCreated(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $target = sys_get_temp_dir() . '/wiki-export-block-' . bin2hex(random_bytes(4));
        file_put_contents($target, 'blocked');

        try {
            $this->expectException(InvalidArgumentException::class);
            (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target . '/nested', WikiInterchangeFormat::Outline);
        } finally {
            unlink($target);
        }
    }

    public function testNotionExportThrowsWhenFolderCannotBeCreated(): void
    {
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Hello</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);
        file_put_contents($target . '/Intro', 'blocks folder creation');

        try {
            $this->expectException(InvalidArgumentException::class);
            (new WikiDocumentExporter(
                $pageRepository,
                new WikiPageTreeBuilder(),
                new WikiMarkdownConverter(),
                new WikiFrontMatterParser(),
            ))->export($space, $target, WikiInterchangeFormat::Notion);
        } finally {
            unlink($target . '/Intro');
            $this->removeTree($target);
        }
    }

    /**
     * 1.15.1 (audit DV-L1 / DV-I4): no page file or folder may take the index name, even case-insensitively.
     */
    public function testPagesNamedLikeTheIndexAreRenamed(): void
    {
        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $readme         = new WikiPage($space, 'readme', 'README.md');
        $taken          = new WikiPage($space, 'readme-1', 'Readme one');
        $child          = new WikiPage($space, 'child', 'README.md', $readme);
        $pageRepository = $this->createStub(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$readme, $taken, $child]);
        $exporter = new WikiDocumentExporter($pageRepository, new WikiPageTreeBuilder(), new WikiMarkdownConverter(), new WikiFrontMatterParser());

        $outline = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        $notion  = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));

        try {
            $exporter->export($space, $outline, WikiInterchangeFormat::Outline);
            self::assertSame(['README.md', 'child.md', 'readme-1.md', 'readme-2.md'], array_values(array_diff(scandir($outline) ?: [], ['.', '..'])));
            self::assertStringContainsString("- [README.md](readme-2.md)\n  - [README.md](child.md)\n- [Readme one](readme-1.md)\n", (string) file_get_contents($outline . '/README.md'));
            self::assertStringContainsString('wiki_slug: readme' . "\n", (string) file_get_contents($outline . '/readme-2.md'));

            $exporter->export($space, $notion, WikiInterchangeFormat::Notion);
            self::assertTrue(WikiExportIndexWriter::isIndexFile($notion . '/README.md'));
            self::assertFileExists($notion . '/README.md-1/README.md-1.md');
            // Only the export root is reserved: the sub-page keeps its name inside the renamed folder.
            self::assertFileExists($notion . '/README.md-1/README.md/README.md.md');
            self::assertStringContainsString('- [README.md](README.md-1/README.md-1.md)', (string) file_get_contents($notion . '/README.md'));
        } finally {
            $this->removeTree($outline);
            $this->removeTree($notion);
        }
    }

    public function testRefusesATargetWithAForeignReadmeBeforeWritingPages(): void
    {
        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page           = new WikiPage($space, 'intro', 'Intro');
        $pageRepository = $this->createStub(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);

        $target = sys_get_temp_dir() . '/wiki-export-' . bin2hex(random_bytes(4));
        mkdir($target);
        file_put_contents($target . '/README.md', "# Mine\n");

        try {
            (new WikiDocumentExporter($pageRepository, new WikiPageTreeBuilder(), new WikiMarkdownConverter(), new WikiFrontMatterParser()))
                ->export($space, $target, WikiInterchangeFormat::Outline);
            self::fail('The export must refuse a foreign README.md.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('not a wiki export index', $exception->getMessage());
        } finally {
            self::assertFileDoesNotExist($target . '/intro.md');
            self::assertSame("# Mine\n", file_get_contents($target . '/README.md'));
            $this->removeTree($target);
        }
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
