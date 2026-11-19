<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\History;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\History\WikiRenderedRevisionDiff;
use Nowo\WikiBundle\History\WikiViewerBlockRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

/**
 * 1.45 (`history.rendered_diff`): {@see WikiRenderedRevisionDiff} — the editor's rendered diff of two Markdown
 * revisions, rendered for the viewer block by block ({@see WikiViewerBlockRenderer}) and sanitized twice, so a
 * revision cannot inject script; HTML revisions return null.
 */
final class WikiRenderedRevisionDiffTest extends TestCase
{
    private WikiPage $page;

    protected function setUp(): void
    {
        $this->page = new WikiPage(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't'), 'run', 'Runbook');
    }

    public function testBlocksAndWordsOfTwoMarkdownRevisions(): void
    {
        $diff = $this->diff()->diff($this->revision(1, "# Title\n\nThe quick fox.\n\nKept."), $this->revision(2, "# Title\n\nThe slow fox.\n\nKept.\n\nNew block."));

        self::assertNotNull($diff);
        self::assertSame(2, $diff->changes);
        self::assertFalse($diff->fallback);
        self::assertStringContainsString('data-nowo-milkdown-diff', $diff->html);
        self::assertStringContainsString('data-change="changed"', $diff->html);
        self::assertStringContainsString('data-change="added"', $diff->html);
        self::assertStringContainsString('<del class="nowo-md-diff-del"><span class="nowo-md-diff-sr">Deleted: </span>quick</del>', $diff->html);
        self::assertStringContainsString('<ins class="nowo-md-diff-ins"><span class="nowo-md-diff-sr">Inserted: </span>slow</ins>', $diff->html);
    }

    public function testNoScriptSurvivesARevision(): void
    {
        $evil = "<script>alert(1)</script>\n\n<img src=x onerror=alert(2)>\n\n[click](javascript:alert(3))\n\n<a href=\"javascript:alert(4)\" onclick=\"x()\">a</a>";
        $diff = $this->diff()->diff($this->revision(1, 'Safe.'), $this->revision(2, "Safe.\n\n" . $evil));

        self::assertNotNull($diff);
        // Raw HTML of the revision is text (escaped), never markup: no element, no handler, no script URL.
        foreach (['<script', '<img', ' onerror=', ' onclick=', 'href="javascript'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $diff->html);
        }
        self::assertStringContainsString('&lt;script&gt;', $diff->html);

        // A renderer that returns markup it should not (a host extension gone wrong): both sanitizer passes hold.
        $hostile = new class implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface {
            public function render(string $markdown): string
            {
                return $this->renderForViewer($markdown);
            }

            public function renderForViewer(string $markdown): string
            {
                return '<p onclick="steal()">' . $markdown . '</p><script>alert(5)</script><iframe src="https://evil.example/"></iframe><ins onmouseover="x()">i</ins><div data-change="x" style="color:red">d</div>';
            }
        };
        $diff = (new WikiRenderedRevisionDiff($hostile, new WikiHtmlSanitizer()))->diff($this->revision(1, 'a'), $this->revision(2, 'b'));
        self::assertNotNull($diff);
        foreach (['<script', ' onclick=', ' onmouseover=', ' style=', 'evil.example'] as $needle) {
            self::assertStringNotContainsStringIgnoringCase($needle, $diff->html);
        }
    }

    public function testHtmlRevisionsAndNeutralRenderersKeepTheLineDiff(): void
    {
        // Audit VC-6: more top-level blocks than the cap on either side keep the line diff.
        $capped = new WikiRenderedRevisionDiff(new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver()), new WikiHtmlSanitizer(), maxBlocks: 2);
        self::assertNull($capped->diff($this->revision(1, "a\n\nb\n\nc"), $this->revision(2, 'a')));
        self::assertNull($capped->diff($this->revision(1, 'a'), $this->revision(2, "a\r\n\r\nb\r\n\r\nc")));
        self::assertNotNull($capped->diff($this->revision(1, 'a'), $this->revision(2, "a\n\nb")));
        self::assertContains('data-position', WikiRenderedRevisionDiff::DIFF_ELEMENTS['div']);

        $html = new WikiPageRevision($this->page, 1, '<p>old</p>', new TestUser('u-1'));
        self::assertNull($this->diff()->diff($html, $this->revision(2, 'new')));
        self::assertNull($this->diff()->diff($this->revision(2, 'new'), $html));
        self::assertNull((new WikiRenderedRevisionDiff(new WikiMarkdownRenderer(), new WikiHtmlSanitizer()))->diff($this->revision(1, 'a'), $this->revision(2, 'b')));
    }

    public function testBlocksRenderInsideThePageFrameThroughTheAttachmentFilter(): void
    {
        $frames  = [];
        $context = new WikiRenderContext();
        $viewer  = new class($context, $frames) implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface {
            /** @param list<WikiRenderFrame|null> $frames */
            public function __construct(private readonly WikiRenderContext $context, private array &$frames)
            {
            }

            public function render(string $markdown): string
            {
                return '';
            }

            public function renderForViewer(string $markdown): string
            {
                $this->frames[] = $this->context->current();

                return '<p>' . $markdown . '</p>';
            }
        };
        $filter = new WikiAttachmentReferenceFilter($this->createStub(WikiAttachmentRepositoryInterface::class));

        $blocks = new WikiViewerBlockRenderer($viewer, new WikiHtmlSanitizer(), $this->page, $context, $filter);
        self::assertSame('<p>text</p>', $blocks->render('text'));
        self::assertNotNull($frames[0]);
        self::assertSame($this->page, $frames[0]->page);
        self::assertNull($context->current());

        $plain = new WikiViewerBlockRenderer($viewer, new WikiHtmlSanitizer(), $this->page);
        self::assertSame('<p>plain</p>', $plain->render('plain'));
        self::assertNull($frames[1]);

        $diff = new WikiRenderedRevisionDiff($viewer, new WikiHtmlSanitizer(), ['abbr' => ['title']], ['data-x'], $context, $filter);
        self::assertNotNull($diff->diff($this->revision(1, 'a'), $this->revision(2, 'b')));
    }

    private function diff(): WikiRenderedRevisionDiff
    {
        $renderer = new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver());

        return new WikiRenderedRevisionDiff($renderer, new WikiHtmlSanitizer(['www.youtube.com'], ['details' => ['open'], 'summary' => []], ['role', 'aria-label', 'data-math', 'data-directive', 'data-icon']));
    }

    private function revision(int $number, string $markdown): WikiPageRevision
    {
        return new WikiPageRevision($this->page, $number, '', new TestUser('u-1'), $markdown);
    }
}
