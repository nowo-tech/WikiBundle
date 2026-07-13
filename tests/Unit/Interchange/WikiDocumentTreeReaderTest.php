<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Interchange\WikiDocumentTreeReader;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use PHPUnit\Framework\TestCase;

use const PHP_OS_FAMILY;

final class WikiDocumentTreeReaderTest extends TestCase
{
    public function testReadsOutlineFlatMarkdown(): void
    {
        $root = sys_get_temp_dir() . '/wiki-outline-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/parent.md', <<<'MD'
---
title: Parent
wiki_slug: parent
---
# Parent

Parent body
MD);
        file_put_contents($root . '/child.md', <<<'MD'
---
title: Child
wiki_slug: child
wiki_parent: parent
---
Child body
MD);

        try {
            $nodes = (new WikiDocumentTreeReader(new WikiFrontMatterParser()))->read($root, WikiInterchangeFormat::Outline);
            self::assertCount(2, $nodes);
            $bySlug = [];
            foreach ($nodes as $node) {
                $bySlug[$node->slug ?? ''] = $node;
            }
            self::assertSame('parent', $bySlug['parent']->slug);
            self::assertSame('parent', $bySlug['child']->parentSlug);
        } finally {
            unlink($root . '/parent.md');
            unlink($root . '/child.md');
            rmdir($root);
        }
    }

    public function testReadsNotionNestedFolders(): void
    {
        $root      = sys_get_temp_dir() . '/wiki-notion-' . bin2hex(random_bytes(4));
        $parentDir = $root . '/Parent Page';
        $childDir  = $parentDir . '/Child Page';
        mkdir($childDir, 0777, true);
        file_put_contents($parentDir . '/Parent Page.md', "# Parent\n\nParent text");
        file_put_contents($childDir . '/Child Page.md', 'Child text');

        try {
            $nodes = (new WikiDocumentTreeReader(new WikiFrontMatterParser()))->read($root, WikiInterchangeFormat::Notion);
            self::assertCount(2, $nodes);
            self::assertSame('Parent', $nodes[0]->title);
            self::assertSame('Parent Page/Child Page', $nodes[1]->relativePath);
            self::assertSame('Parent Page', $nodes[1]->parentRelativePath);
        } finally {
            unlink($childDir . '/Child Page.md');
            unlink($parentDir . '/Parent Page.md');
            rmdir($childDir);
            rmdir($parentDir);
            rmdir($root);
        }
    }

    public function testNormalizesExportWrapperDirectory(): void
    {
        $root    = sys_get_temp_dir() . '/wiki-outline-' . bin2hex(random_bytes(4));
        $wrapper = $root . '/Export-Team-Wiki';
        mkdir($wrapper, 0777, true);
        file_put_contents($wrapper . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Body
MD);

        try {
            $nodes = (new WikiDocumentTreeReader(new WikiFrontMatterParser()))->read($root, WikiInterchangeFormat::Outline);
            self::assertCount(1, $nodes);
            self::assertSame('intro', $nodes[0]->slug);
        } finally {
            unlink($wrapper . '/intro.md');
            rmdir($wrapper);
            rmdir($root);
        }
    }

    public function testNotionUsesSingleMarkdownFallback(): void
    {
        $root = sys_get_temp_dir() . '/wiki-notion-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/only.md', "# Only\n\nText");

        try {
            $nodes = (new WikiDocumentTreeReader(new WikiFrontMatterParser()))->read($root, WikiInterchangeFormat::Notion);
            self::assertCount(1, $nodes);
            self::assertSame('Only', $nodes[0]->title);
        } finally {
            unlink($root . '/only.md');
            rmdir($root);
        }
    }

    public function testOutlineUsesFirstMarkdownWhenMultipleExist(): void
    {
        $root = sys_get_temp_dir() . '/wiki-outline-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/alpha.md', "# Alpha\n\nA");
        file_put_contents($root . '/beta.md', "# Beta\n\nB");

        try {
            $nodes = (new WikiDocumentTreeReader(new WikiFrontMatterParser()))->read($root, WikiInterchangeFormat::Outline);
            self::assertCount(2, $nodes);
        } finally {
            unlink($root . '/alpha.md');
            unlink($root . '/beta.md');
            rmdir($root);
        }
    }

    public function testWalkSkipsUnreadableDirectories(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('chmod-based unreadable directory test is Unix-specific.');
        }

        $root = sys_get_temp_dir() . '/wiki-tree-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/visible.md', "# Visible\n\nBody");
        $hidden = $root . '/hidden';
        mkdir($hidden);
        chmod($hidden, 0000);

        try {
            $nodes = (new WikiDocumentTreeReader(new WikiFrontMatterParser()))->read($root, WikiInterchangeFormat::Outline);
            self::assertCount(1, $nodes);
        } finally {
            chmod($hidden, 0700);
            rmdir($hidden);
            unlink($root . '/visible.md');
            rmdir($root);
        }
    }
}
