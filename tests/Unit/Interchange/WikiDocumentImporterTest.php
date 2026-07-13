<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use InvalidArgumentException;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiArchiveHelper;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use ZipArchive;

final class WikiDocumentImporterTest extends TestCase
{
    public function testImportsOutlineMarkdownDirectory(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome to the wiki.
MD);

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->pageRepository();

        $importer = $this->importer($pageRepository);

        try {
            $report = $importer->import($space, $root, WikiInterchangeFormat::Outline, new TestUser());
            self::assertSame(1, $report->created);
            self::assertSame(0, $report->failed);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testImportsFromZipArchive(): void
    {
        $sourceDir = sys_get_temp_dir() . '/wiki-import-src-' . bin2hex(random_bytes(4));
        mkdir($sourceDir);
        file_put_contents($sourceDir . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $zipPath = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4)) . '.zip';
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sourceDir . '/intro.md', 'intro.md');
        $zip->close();

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->pageRepository();

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $zipPath,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );

            self::assertSame(1, $report->created);
        } finally {
            unlink($sourceDir . '/intro.md');
            rmdir($sourceDir);
            unlink($zipPath);
        }
    }

    public function testAutoDetectsFormat(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->pageRepository();

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Auto,
                new TestUser(),
            );

            self::assertSame(1, $report->created);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testReportsEmptySource(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);

        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');

        try {
            $report = $this->importer($this->pageRepository())->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );

            self::assertSame(['No Markdown documents found in import source.'], $report->messages);
        } finally {
            rmdir($root);
        }
    }

    public function testRejectsMissingSource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->importer($this->pageRepository())->import(
            new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1'),
            '/tmp/missing-' . bin2hex(random_bytes(4)),
            WikiInterchangeFormat::Outline,
            new TestUser(),
        );
    }

    public function testSkipsExistingPageWhenNotUpdating(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page           = new WikiPage($space, 'intro', 'Intro');
        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(1);

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );

            self::assertSame(1, $report->skipped);
            self::assertStringContainsString('Skipped existing page', $report->messages[0]);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testUpdatesExistingPage(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Updated body.
MD);

        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Old</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(1);
        $pageRepository->expects(self::atLeastOnce())->method('save');

        $revisionRepository = $this->createMock(\Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface::class);
        $revisionRepository->method('getNextRevisionNumber')->willReturn(2);
        $revisionRepository->expects(self::once())->method('save');

        try {
            $report = $this->importer($pageRepository, $revisionRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
                true,
            );

            self::assertSame(1, $report->updated);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testDryRunUpdateCountsWithoutSaving(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Updated.
MD);

        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(1);
        $pageRepository->expects(self::never())->method('save');

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
                true,
                true,
            );

            self::assertSame(1, $report->updated);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testEnsureUniqueSlugThrowsForDuplicateSlugInDatabase(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: archived-intro
---
Welcome.
MD);

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(1);

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );

            self::assertSame(1, $report->failed);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testDryRunDoesNotPersist(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Welcome.
MD);

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepository->expects(self::never())->method('save');

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
                false,
                true,
            );

            self::assertSame(1, $report->created);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testImportNodeFailureIncrementsFailed(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/bad.md', <<<'MD'
---
title: Bad
wiki_slug: invalid slug
---
Body
MD);

        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');

        try {
            $report = $this->importer($this->pageRepository())->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );

            self::assertSame(1, $report->failed);
        } finally {
            unlink($root . '/bad.md');
            rmdir($root);
        }
    }

    public function testResolvesParentByRelativePath(): void
    {
        $root      = sys_get_temp_dir() . '/wiki-notion-import-' . bin2hex(random_bytes(4));
        $parentDir = $root . '/Parent Page';
        $childDir  = $parentDir . '/Child Page';
        mkdir($childDir, 0777, true);
        file_put_contents($parentDir . '/Parent Page.md', "# Parent\n\nParent text");
        file_put_contents($childDir . '/Child Page.md', 'Child text');

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->pageRepository();
        $pageRepository->expects(self::exactly(4))->method('save');

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Notion,
                new TestUser(),
            );

            self::assertSame(2, $report->created);
        } finally {
            unlink($childDir . '/Child Page.md');
            unlink($parentDir . '/Parent Page.md');
            rmdir($childDir);
            rmdir($parentDir);
            rmdir($root);
        }
    }

    public function testGeneratesUniqueSlugWhenDatabaseHasConflict(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
---
Welcome.
MD);

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([]);
        $pageRepository->method('countBySpaceAndSlug')->willReturnCallback(
            static fn (WikiSpace $s, string $slug): int => $slug === 'intro' ? 1 : 0,
        );
        $pageRepository->expects(self::exactly(2))->method('save');

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );

            self::assertSame(1, $report->created);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testResolvesParentBySlug(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/aaa-parent.md', <<<'MD'
---
title: Parent
wiki_slug: parent
---
Parent body
MD);
        file_put_contents($root . '/zzz-child.md', <<<'MD'
---
title: Child
wiki_slug: child
wiki_parent: parent
---
Child body
MD);

        $space          = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $pageRepository = $this->pageRepository();
        $pageRepository->expects(self::exactly(4))->method('save');

        try {
            $report = $this->importer($pageRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );

            self::assertSame(2, $report->created);
        } finally {
            unlink($root . '/aaa-parent.md');
            unlink($root . '/zzz-child.md');
            rmdir($root);
        }
    }

    private function pageRepository(): WikiPageRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
    {
        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepository->method('save');

        return $pageRepository;
    }

    private function importer(
        WikiPageRepositoryInterface $pageRepository,
        ?\Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface $revisionRepository = null,
    ): WikiDocumentImporter {
        $revisionRepository ??= $this->createConfiguredMock(
            \Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface::class,
            [
                'getNextRevisionNumber' => 1,
            ],
        );
        $revisionRepository->method('save');

        $pageService = new WikiPageService(
            $pageRepository,
            $revisionRepository,
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        return new WikiDocumentImporter(
            new WikiArchiveHelper(),
            new WikiFormatDetector(new WikiFrontMatterParser()),
            new WikiDocumentTreeReader(new WikiFrontMatterParser()),
            new WikiMarkdownConverter(),
            $pageRepository,
            $pageService,
            new WikiSlugger(),
        );
    }
}
