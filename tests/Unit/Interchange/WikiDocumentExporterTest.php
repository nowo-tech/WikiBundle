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
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

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
        } finally {
            unlink($target . '/parent.md');
            unlink($target . '/child.md');
            rmdir($target);
        }
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
            if (is_dir($target)) {
                rmdir($target);
            }
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
            rmdir($target);
        }
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
            rmdir($target);
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
