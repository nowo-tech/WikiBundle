<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use Nowo\WikiBundle\Enum\WikiInterchangeFormat;
use Nowo\WikiBundle\Interchange\WikiFormatDetector;
use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use PHPUnit\Framework\TestCase;

final class WikiFormatDetectorTest extends TestCase
{
    public function testDetectsOutlineLayout(): void
    {
        $root = sys_get_temp_dir() . '/wiki-detect-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Body
MD);

        try {
            $format = (new WikiFormatDetector(new WikiFrontMatterParser()))->detect($root);
            self::assertSame(WikiInterchangeFormat::Outline, $format);
        } finally {
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testDetectsNotionLayout(): void
    {
        $root      = sys_get_temp_dir() . '/wiki-detect-' . bin2hex(random_bytes(4));
        $parentDir = $root . '/Parent Page';
        mkdir($parentDir, 0777, true);
        file_put_contents($parentDir . '/Parent Page.md', "# Parent\n\nText");

        try {
            $format = (new WikiFormatDetector(new WikiFrontMatterParser()))->detect($root);
            self::assertSame(WikiInterchangeFormat::Notion, $format);
        } finally {
            unlink($parentDir . '/Parent Page.md');
            rmdir($parentDir);
            rmdir($root);
        }
    }

    public function testIgnoresNonMarkdownFiles(): void
    {
        $root = sys_get_temp_dir() . '/wiki-detect-' . bin2hex(random_bytes(4));
        mkdir($root);
        file_put_contents($root . '/notes.txt', 'plain text');
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Body
MD);

        try {
            $format = (new WikiFormatDetector(new WikiFrontMatterParser()))->detect($root);
            self::assertSame(WikiInterchangeFormat::Outline, $format);
        } finally {
            unlink($root . '/notes.txt');
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }

    public function testDetectsOutlineWhenSubdirectoriesExist(): void
    {
        $root = sys_get_temp_dir() . '/wiki-detect-' . bin2hex(random_bytes(4));
        mkdir($root);
        mkdir($root . '/assets');
        file_put_contents($root . '/intro.md', <<<'MD'
---
title: Intro
wiki_slug: intro
---
Body
MD);

        try {
            $format = (new WikiFormatDetector(new WikiFrontMatterParser()))->detect($root);
            self::assertSame(WikiInterchangeFormat::Outline, $format);
        } finally {
            rmdir($root . '/assets');
            unlink($root . '/intro.md');
            rmdir($root);
        }
    }
}
