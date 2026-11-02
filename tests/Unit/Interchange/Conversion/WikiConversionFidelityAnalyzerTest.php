<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Interchange\Conversion;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\Interchange\Conversion\WikiConversionFidelity;
use Nowo\WikiBundle\Interchange\Conversion\WikiConversionFidelityAnalyzer;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class WikiConversionFidelityAnalyzerTest extends TestCase
{
    public function testALosslessConversion(): void
    {
        $html     = '<h2>Setup</h2><p>Use <strong>this</strong> &amp; <a href="https://x.test">that</a>.</p><ul><li><p>one</p></li></ul><pre><code>code()</code></pre>';
        $fidelity = $this->analyze($html, false);

        self::assertSame([], $fidelity->lost);
        self::assertSame([], $fidelity->approximated);
        self::assertTrue($fidelity->textPreserved());
        self::assertTrue($fidelity->isLossless());
        self::assertSame(1.0, $fidelity->similarity);
        self::assertSame([
            'lost'         => [],
            'approximated' => [],
            'text'         => ['preserved' => true, 'similarity' => 1.0, 'words_missing' => 0, 'words_added' => 0],
        ], $fidelity->toArray());
    }

    public function testLostConstructsWithGfm(): void
    {
        $html = '<p><u>under</u> H<sub>2</sub>O x<sup>2</sup> <mark>hot</mark></p><p style="text-align: center">mid</p>'
            . '<p style="text-align: justify">just</p><img src="/a.png" width="20"><ol type="a"><li>a</li></ol><ol type="1"><li>b</li></ol>'
            . '<iframe src="https://www.youtube.com/embed/x"></iframe><abbr>abbr</abbr>'
            . '<table><tr><td colwidth="100" colspan="2"><p>a</p><p>b</p></td><td style="width: 10px"><ul><li>c</li></ul></td></tr></table>'
            . '<figure><img src="/b.png"><figcaption>cap</figcaption></figure><table><caption>t</caption><tr><th>h</th></tr><tr><td><table><tr><th>n</th></tr></table></td></tr></table>';

        $fidelity = $this->analyze($html, false);

        self::assertSame([
            'alignment'    => 2,
            'column_width' => 2,
            'element:abbr' => 1,
            'embed'        => 1,
            'highlight'    => 1,
            'image_size'   => 1,
            'list_style'   => 1,
            'subscript'    => 1,
            'superscript'  => 1,
            'underline'    => 1,
        ], $fidelity->lost);
        self::assertSame([
            'figure_caption'       => 1,
            'nested_table'         => 1,
            'table_caption'        => 1,
            'table_cell_blocks'    => 2,
            'table_cell_span'      => 1,
            'table_without_header' => 1,
        ], $fidelity->approximated);
        self::assertFalse($fidelity->isLossless());
    }

    public function testTheDialectKeepsHighlightAlignmentAndLinksEmbeds(): void
    {
        $fidelity = $this->analyze('<p><mark>hot</mark></p><p style="text-align:right">r</p><iframe src="https://player.vimeo.com/video/1"></iframe>', true);

        self::assertSame([], $fidelity->lost);
        self::assertSame(['embed_as_link' => 1], $fidelity->approximated);
    }

    public function testWordsAreComparedWithoutDialectTokens(): void
    {
        $renderer = new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver('/s.svg', ['home']));
        $analyzer = new WikiConversionFidelityAnalyzer($renderer);

        $same = $analyzer->analyze('<p>Read [[wiki:0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10]] :tabler-home: now</p>', 'Read [[wiki:0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10]] :tabler-home: now', true);
        self::assertTrue($same->textPreserved());

        $changed = $analyzer->analyze('<p>one two three four</p>', 'one two five', true);
        self::assertSame(2, $changed->wordsMissing);
        self::assertSame(1, $changed->wordsAdded);
        self::assertSame(0.5714, $changed->similarity);
        self::assertFalse($changed->isLossless());

        self::assertSame(1.0, $analyzer->analyze('', '', true)->similarity);
    }

    public function testDocumentsOverTheRendererLimitsAreReported(): void
    {
        $renderer = new MilkdownWikiMarkdownRenderer(['max_inline_markers' => 2], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver());

        $fidelity = (new WikiConversionFidelityAnalyzer($renderer))->analyze('<p><em>a</em> <em>b</em></p>', '*a* *b*', true);

        self::assertSame(['render_limit' => 1], $fidelity->lost);
    }

    private function analyze(string $html, bool $dialect): WikiConversionFidelity
    {
        return (new WikiConversionFidelityAnalyzer())->analyze($html, (new WikiMarkdownConverter($dialect))->htmlToMarkdown($html), $dialect);
    }
}
