<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

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
        $space = new WikiSpace('docs', 'Docs', WikiSpaceOwnerScope::Team, 'team-1');
        $page  = new WikiPage($space, 'intro', 'Intro');
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
            ))->export($space, $target, WikiInterchangeFormat::Outline);

            self::assertSame(1, $report->pagesExported);
            self::assertFileExists($target . '/intro.md');
            $content = (string) file_get_contents($target . '/intro.md');
            self::assertStringContainsString('wiki_slug: intro', $content);
            self::assertStringContainsString('Hello', $content);
        } finally {
            unlink($target . '/intro.md');
            rmdir($target);
        }
    }
}
