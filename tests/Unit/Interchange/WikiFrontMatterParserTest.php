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

    public function testSerializedFrontMatterParsesBack(): void
    {
        // 1.15.1 (audit DV-I8): the closing delimiter used to follow the last value (`wiki_parent: ''---`).
        $parser  = new WikiFrontMatterParser();
        $meta    = ['title' => 'Setup: step 1', 'wiki_slug' => 'setup', 'wiki_parent' => ''];
        $content = $parser->serialize($meta, "## Section\n\nText\n\n---\n\nAfter a rule");

        self::assertStringStartsWith("---\ntitle: 'Setup: step 1'\nwiki_slug: setup\nwiki_parent: ''\n---\n\n## Section", $content);
        self::assertSame(['meta' => $meta, 'body' => "## Section\n\nText\n\n---\n\nAfter a rule"], $parser->parse($content));
    }

    public function testReturnsBodyWhenFrontMatterIsUnclosed(): void
    {
        $parsed = (new WikiFrontMatterParser())->parse("---\ntitle: Broken\nBody without closing delimiter");

        self::assertSame([], $parsed['meta']);
        self::assertStringContainsString('Body without closing delimiter', $parsed['body']);
    }

    public function testParsesWindowsLineEndings(): void
    {
        $parsed = (new WikiFrontMatterParser())->parse("---\r\ntitle: Windows\r\n---\r\n\r\nBody");

        self::assertSame('Windows', $parsed['meta']['title']);
        self::assertSame('Body', trim($parsed['body']));
    }

    public function testReturnsBodyWhenYamlIsInvalid(): void
    {
        $parsed = (new WikiFrontMatterParser())->parse("---\ntitle: [broken\n---\n\nBody");

        self::assertSame([], $parsed['meta']);
        self::assertStringContainsString('Body', $parsed['body']);
    }

    public function testSerializeReturnsBodyOnlyWhenMetaEmpty(): void
    {
        self::assertSame('Plain body', (new WikiFrontMatterParser())->serialize([], 'Plain body'));
    }
}
