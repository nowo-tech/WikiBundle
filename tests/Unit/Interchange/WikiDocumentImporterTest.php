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
use Nowo\WikiBundle\Interchange\WikiDocumentExporter;
use Nowo\WikiBundle\Interchange\WikiDocumentImporter;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Util\WikiSlugger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use ZipArchive;

use function array_diff;
use function array_keys;
use function array_values;
use function in_array;

use const JSON_THROW_ON_ERROR;

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

    public function testLogsStartAndFinishWithCounters(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', "# Intro\n\nSecret body text.");
        $logger = new RecordingLogger();

        try {
            $this->importer($this->pageRepository(), logger: $logger)->import(
                new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1'),
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }

        self::assertSame(['info: Wiki import started.', 'info: Wiki import finished.'], $logger->lines());
        self::assertSame('docs', $logger->records[1]['context']['space']);
        self::assertSame(1, $logger->records[1]['context']['created']);
        self::assertStringNotContainsString('Secret body', json_encode($logger->records, JSON_THROW_ON_ERROR));
    }

    public function testLogsFailureAndRethrows(): void
    {
        $logger = new RecordingLogger();

        try {
            $this->importer($this->pageRepository(), logger: $logger)->import(
                new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1'),
                '/tmp/missing-' . bin2hex(random_bytes(4)),
                WikiInterchangeFormat::Outline,
                new TestUser(),
            );
            self::fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException) {
        }

        self::assertSame(['info: Wiki import started.', 'error: Wiki import failed.'], $logger->lines());
        self::assertSame(InvalidArgumentException::class, $logger->records[1]['context']['exception_class']);
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

        $revisionRepository = $this->createMock(WikiPageRevisionRepositoryInterface::class);
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

    public function testMarkdownEditorStoresTheMarkdownOfCreatedAndUpdatedPages(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', "---\ntitle: Intro\nwiki_slug: intro\n---\n| A | B |\n|---|---|\n| 1 | 2 |\n");
        file_put_contents($root . '/new.md', "---\ntitle: New\nwiki_slug: new\n---\n- [ ] todo\n");

        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
        $page->setCurrentRevision(new WikiPageRevision($page, 1, '<p>Old</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$page]);
        $pageRepository->method('countBySpaceAndSlug')->willReturnCallback(static fn (WikiSpace $space, string $slug): int => $slug === 'intro' ? 1 : 0);

        $saved              = [];
        $revisionRepository = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepository->method('getNextRevisionNumber')->willReturn(2);
        $revisionRepository->method('save')->willReturnCallback(static function (WikiPageRevision $revision) use (&$saved): void {
            $saved[$revision->getPage()->getSlug()] = $revision;
        });

        try {
            $report = $this->importer($pageRepository, $revisionRepository, null, 'markdown')
                ->import($space, $root, WikiInterchangeFormat::Outline, new TestUser(), true);

            self::assertSame(1, $report->updated);
            self::assertSame(1, $report->created);
            self::assertSame("| A | B |\n|---|---|\n| 1 | 2 |", $saved['intro']->getContentMarkdown());
            self::assertStringContainsString('<table>', $saved['intro']->getContentHtml());
            self::assertTrue($saved['new']->isMarkdown());
            self::assertSame('- [ ] todo', $saved['new']->getContentMarkdown());
        } finally {
            unlink($root . '/intro.md');
            unlink($root . '/new.md');
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

    /**
     * 1.13.2 (audit DJ-L1): a page the importing user may not read is never updated, matched or used as a parent; its
     * slug is "not available" (no duplicate created, no "skipped existing" oracle).
     */
    public function testPagesTheUserMayNotReadAreNeverMatchedUpdatedOrUsedAsParents(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        $files = [
            'a-secret.md'  => "---\ntitle: Secret\nwiki_slug: secret\n---\nOverwrite attempt.\n",
            'b-derived.md' => "---\ntitle: Secret\n---\nDerived slug of a hidden page.\n",
            'c-child.md'   => "---\ntitle: Child\nwiki_slug: child\nwiki_parent: secret\n---\nUnder a hidden parent.\n",
            'd-public.md'  => "---\ntitle: Public\nwiki_slug: public\n---\nVisible page updated.\n",
        ];
        foreach ($files as $name => $body) {
            file_put_contents($root . '/' . $name, $body);
        }

        $space  = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $secret = new WikiPage($space, 'secret', 'Secret');
        $public = new WikiPage($space, 'public', 'Public');
        $public->setCurrentRevision(new WikiPageRevision($public, 1, '<p>Old</p>', new TestUser()));

        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([$secret, $public]);
        $pageRepository->method('countBySpaceAndSlug')->willReturnCallback(static fn (WikiSpace $s, string $slug): int => in_array($slug, ['secret', 'public'], true) ? 1 : 0);
        $saved = [];
        $pageRepository->method('save')->willReturnCallback(static function (WikiPage $page) use (&$saved): void {
            $saved[] = $page->getSlug();
        });

        $revisionRepository = $this->createMock(WikiPageRevisionRepositoryInterface::class);
        $revisionRepository->method('getNextRevisionNumber')->willReturn(2);

        try {
            $report = $this->importer($pageRepository, $revisionRepository)->import(
                $space,
                $root,
                WikiInterchangeFormat::Outline,
                new TestUser(),
                true,
                false,
                [$public->getId() => true],
            );

            self::assertSame([0, 1, 3], [$report->created, $report->updated, $report->failed]);
            self::assertCount(3, $report->messages);
            self::assertStringEndsWith('Slug "secret" is not available.', $report->messages[0]);
            self::assertStringEndsWith('Slug "secret" is not available.', $report->messages[1]);
            self::assertStringEndsWith('Parent "secret" is not available.', $report->messages[2]);
            self::assertNotContains('secret', $saved);
            self::assertNotContains('secret-2', $saved);
        } finally {
            foreach (array_keys($files) as $name) {
                unlink($root . '/' . $name);
            }
            rmdir($root);
        }
    }

    public function testAMissingParentReadsLikeAHiddenOneInAUsersImportButNotInTheCli(): void
    {
        $root = sys_get_temp_dir() . '/wiki-import-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/a-new.md', "---\ntitle: New\nwiki_slug: new-parent\n---\nA parent created by this import.\n");
        file_put_contents($root . '/b-child.md', "---\ntitle: Child\nwiki_slug: child\nwiki_parent: nowhere\n---\nMissing parent.\n");
        file_put_contents($root . '/c-child.md', "---\ntitle: Other\nwiki_slug: other\nwiki_parent: new-parent\n---\nParent from this import.\n");
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');

        try {
            foreach ([false, true] as $dryRun) {
                $web = $this->importer($this->pageRepository())->import($space, $root, WikiInterchangeFormat::Outline, new TestUser(), false, $dryRun, []);
                self::assertSame([2, 1], [$web->created, $web->failed]);
                self::assertStringEndsWith('Parent "nowhere" is not available.', $web->messages[0]);
            }

            $cli = $this->importer($this->pageRepository())->import($space, $root, WikiInterchangeFormat::Outline, new TestUser());
            self::assertSame([3, 0], [$cli->created, $cli->failed]);
        } finally {
            foreach (['a-new.md', 'b-child.md', 'c-child.md'] as $name) {
                unlink($root . '/' . $name);
            }
            rmdir($root);
        }
    }

    /**
     * 1.15.1 round trip: an Outline export (README.md index, a page whose slug is "readme" written as readme-1.md)
     * imports back with titles, slugs and parents from the front matter; the index is reported as skipped.
     */
    public function testReimportsAnOutlineExportAndReportsTheSkippedIndex(): void
    {
        $space  = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $intro  = new WikiPage($space, 'intro', 'Intro');
        $readme = new WikiPage($space, 'readme', 'README', $intro);
        $intro->setCurrentRevision(new WikiPageRevision($intro, 1, '<p>Hello</p>', new TestUser()));
        $readme->setCurrentRevision(new WikiPageRevision($readme, 1, '<p>Read me</p>', new TestUser()));
        $source = $this->createStub(WikiPageRepositoryInterface::class);
        $source->method('findActiveBySpace')->willReturn([$intro, $readme]);

        $root = sys_get_temp_dir() . '/wiki-roundtrip-' . bin2hex(random_bytes(4));
        (new WikiDocumentExporter($source, new WikiPageTreeBuilder(), new WikiMarkdownConverter(), new WikiFrontMatterParser()))
            ->export($space, $root, WikiInterchangeFormat::Outline);

        $pageRepository = $this->pageRepository();
        $saved          = [];
        $pageRepository->method('save')->willReturnCallback(static function (WikiPage $page) use (&$saved): void {
            $saved[$page->getSlug()] = [$page->getTitle(), $page->getParent()?->getSlug()];
        });

        try {
            self::assertSame(['README.md', 'intro.md', 'readme-1.md'], array_values(array_diff(scandir($root) ?: [], ['.', '..'])));

            $report = $this->importer($pageRepository)->import($space, $root, WikiInterchangeFormat::Outline, new TestUser());

            self::assertSame([2, 1, 0], [$report->created, $report->skipped, $report->failed]);
            self::assertSame(['README.md: wiki export index, skipped.'], $report->messages);
            self::assertSame(['intro' => ['Intro', null], 'readme' => ['README', 'intro']], $saved);
        } finally {
            (new WikiArchiveHelper())->removeDirectory($root);
        }
    }

    private function pageRepository(): WikiPageRepositoryInterface&MockObject
    {
        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepository->method('save');

        return $pageRepository;
    }

    private function importer(
        WikiPageRepositoryInterface $pageRepository,
        ?WikiPageRevisionRepositoryInterface $revisionRepository = null,
        ?RecordingLogger $logger = null,
        string $editorType = 'tiptap',
    ): WikiDocumentImporter {
        $revisionRepository ??= $this->createConfiguredMock(
            WikiPageRevisionRepositoryInterface::class,
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
            $logger,
            $editorType,
        );
    }
}
