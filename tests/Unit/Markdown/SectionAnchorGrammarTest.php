<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use Nowo\WikiBundle\Interchange\HtmlToMarkdown\WikiHeadingIdConverter;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Interchange\WikiMarkdownPortableLinks;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceExtractor;
use PHPUnit\Framework\TestCase;

/**
 * 1.40 (contract §1 Q1 points 1 and 4, W1): the token anchor grammar is the heading id grammar everywhere — the
 * reference extractor fallback, portable exports, the dialect text converter — and headings keep a differing id as the
 * `{#id}` suffix when HTML is turned back into Markdown.
 */
final class SectionAnchorGrammarTest extends TestCase
{
    private const ID = '0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10';

    public function testTheExtractorFallbackReadsTheHeadingIdGrammar(): void
    {
        $extractor = new WikiMarkdownReferenceExtractor();
        $markdown  = '[[wiki:' . self::ID . '#año]] [[wiki:' . self::ID . '#nowo-h-setup]] [[wiki:' . self::ID . '#Bad_Anchor]] [[wiki:0B6F4F0E-3C55-4D0A-9A43-1F7A2C9E8D10]]';

        self::assertSame([
            ['id' => self::ID, 'anchor' => 'año'],
            ['id' => self::ID, 'anchor' => 'setup'],
            ['id' => self::ID, 'anchor' => null],
        ], $extractor->pageReferences($markdown));
    }

    public function testPortableLinksKeepValidAnchorsOnly(): void
    {
        $exported = [self::ID => ['path' => '/x/other.md', 'title' => 'Other']];

        self::assertSame('[Other](other.md#año)', WikiMarkdownPortableLinks::rewrite('[[wiki:' . self::ID . '#año]]', '/x', $exported));
        self::assertSame('[Other](other.md#setup)', WikiMarkdownPortableLinks::rewrite('[[wiki:' . self::ID . '#nowo-h-setup]]', '/x', $exported));
        self::assertSame('[Other](other.md)', WikiMarkdownPortableLinks::rewrite('[[wiki:' . self::ID . '#a--b]]', '/x', $exported));
    }

    public function testTheDialectTextConverterKeepsValidAnchors(): void
    {
        $converter = new WikiMarkdownConverter(true);

        self::assertSame('See [[wiki:' . self::ID . '#año]]', $converter->htmlToMarkdown('<p>See [[wiki:' . self::ID . '#año]]</p>'));
        self::assertSame('See [[wiki:' . self::ID . ']]', $converter->htmlToMarkdown('<p>See [[wiki:' . self::ID . '#Upper]]</p>'));
    }

    public function testHeadingsKeepADifferingIdAsTheSuffix(): void
    {
        $converter = new WikiMarkdownConverter(true);

        self::assertSame('## Install guide {#setup}', $converter->htmlToMarkdown('<h2 id="nowo-h-setup">Install guide</h2>'));
        self::assertSame('## Plain', $converter->htmlToMarkdown('<h2>Plain</h2>'));
        self::assertSame('## Ignored', $converter->htmlToMarkdown('<h2 id="host-id">Ignored</h2>'));
        self::assertSame('## Text \{#x}', $converter->htmlToMarkdown('<h2>Text {#x}</h2>'));
        self::assertSame("::: {align=center}\n## Centred {#c}\n:::", $converter->htmlToMarkdown('<h2 id="nowo-h-c" style="text-align: center">Centred</h2>'));
        self::assertSame('', $converter->htmlToMarkdown('<h2 id="nowo-h-x"></h2>'));
    }

    public function testOnlyIdsThatDifferFromTheSlugAreKept(): void
    {
        $html = '<h2 id="nowo-h-install-guide">Install <em>guide</em></h2><h2 id="nowo-h-intro-2">Intro</h2><h3 class="x" id="nowo-h-old-name" title="t">New name</h3><h2 id="nowo-h-Bad">Bad</h2>';

        self::assertSame(
            '<h2>Install <em>guide</em></h2><h2 id="nowo-h-intro-2">Intro</h2><h3 class="x" id="nowo-h-old-name" title="t">New name</h3><h2>Bad</h2>',
            WikiHeadingIdConverter::keepDifferentIds($html),
        );
    }

    /** Audit RY-L1: the slug is taken from the text the id sees, without references, mentions, icons and math. */
    public function testReferencesMentionsIconsAndMathDoNotCountForTheSlug(): void
    {
        $html = '<h2 id="nowo-h-see-now">See <a class="nowo-md-ref" href="/p">Other page</a> now</h2>'
            . '<h2 id="nowo-h-ask-x">Ask <span class="nowo-md-mention">@ana</span><span class="nowo-md-icon" data-icon="home"></span> <code>x</code><span class="nowo-md-math">y</span></h2>';

        self::assertSame('<h2>See <a class="nowo-md-ref" href="/p">Other page</a> now</h2><h2>Ask <span class="nowo-md-mention">@ana</span><span class="nowo-md-icon" data-icon="home"></span> <code>x</code><span class="nowo-md-math">y</span></h2>', WikiHeadingIdConverter::keepDifferentIds($html));
    }
}
