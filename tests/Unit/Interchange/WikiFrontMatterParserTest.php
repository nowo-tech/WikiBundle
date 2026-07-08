<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange;

use Nowo\WikiBundle\Interchange\WikiFrontMatterParser;
use PHPUnit\Framework\TestCase;

final class WikiFrontMatterParserTest extends TestCase
{
    public function testParsesYamlFrontMatter(): void
    {
        $parsed = (new WikiFrontMatterParser())->parse(<<<'MD'
---
title: Hello
wiki_slug: hello
---

# Hello

Body
MD);

        self::assertSame('Hello', $parsed['meta']['title']);
        self::assertSame('hello', $parsed['meta']['wiki_slug']);
        self::assertStringContainsString('# Hello', $parsed['body']);
    }

    public function testReturnsBodyWhenNoFrontMatter(): void
    {
        $parsed = (new WikiFrontMatterParser())->parse("# Title\n\nText");

        self::assertSame([], $parsed['meta']);
        self::assertSame("# Title\n\nText", $parsed['body']);
    }

    public function testSerializeWrapsMetaAndBody(): void
    {
        $content = (new WikiFrontMatterParser())->serialize(
            ['title' => 'Page', 'wiki_slug' => 'page'],
            "## Section\n\nText",
        );

        self::assertStringStartsWith("---\n", $content);
        self::assertStringContainsString('wiki_slug: page', $content);
        self::assertStringContainsString('## Section', $content);
    }
}
