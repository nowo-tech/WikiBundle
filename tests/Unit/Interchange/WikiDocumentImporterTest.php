<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

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
        $pageRepository = $this->createMock(WikiPageRepositoryInterface::class);
        $pageRepository->method('findActiveBySpace')->willReturn([]);
        $pageRepository->method('countBySpaceAndSlug')->willReturn(0);
        $pageRepository->expects(self::exactly(2))->method('save');

        $revisionRepository = $this->createMock(\Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface::class);
        $revisionRepository->method('getNextRevisionNumber')->willReturn(1);
        $revisionRepository->expects(self::once())->method('save');

        $pageService = new WikiPageService(
            $pageRepository,
            $revisionRepository,
            new WikiSlugger(),
            new WikiHtmlSanitizer(),
            new EventDispatcher(),
        );

        $importer = new WikiDocumentImporter(
            new WikiArchiveHelper(),
            new WikiFormatDetector(new WikiFrontMatterParser()),
            new WikiDocumentTreeReader(new WikiFrontMatterParser()),
            new WikiMarkdownConverter(),
            $pageRepository,
            $pageService,
            new WikiSlugger(),
        );

        try {
            $report = $importer->import($space, $root, WikiInterchangeFormat::Outline, new TestUser());
            self::assertSame(1, $report->created);
            self::assertSame(0, $report->failed);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }
}
