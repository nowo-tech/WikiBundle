<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiRevisionBodyRenderer;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryAttachmentRepository;
use PHPUnit\Framework\TestCase;

final class WikiRevisionBodyRendererTest extends TestCase
{
    private WikiPage $page;

    protected function setUp(): void
    {
        $this->page = new WikiPage(new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1'), 'p', 'P');
    }

    public function testStoredHtmlWithANeutralRendererOrAnHtmlRevision(): void
    {
        $body = new WikiRevisionBodyRenderer(new WikiMarkdownRenderer(), new WikiHtmlSanitizer());

        self::assertSame('<p>cached</p>', $body->body(new WikiPageRevision($this->page, 1, '<p>cached</p>', new TestUser(), '*source*')));
        self::assertFalse($body->hasRichContent());

        $viewer = new WikiRevisionBodyRenderer($this->viewerRenderer(), new WikiHtmlSanitizer());
        self::assertSame('<p>html</p>', $viewer->body(new WikiPageRevision($this->page, 1, '<p>html</p>', new TestUser())));
        self::assertTrue($viewer->hasRichContent());
    }

    public function testMarkdownIsRenderedForTheViewerThenSanitizedAndFiltered(): void
    {
        $revision = new WikiPageRevision($this->page, 1, '<p>cached</p>', new TestUser(), 'source');

        $plain = new WikiRevisionBodyRenderer($this->viewerRenderer(), new WikiHtmlSanitizer());
        self::assertSame('<p>viewer: source</p><a href="/wiki/attachments/0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10">f</a>', $plain->body($revision));

        $filtered = new WikiRevisionBodyRenderer($this->viewerRenderer(), new WikiHtmlSanitizer(), new WikiAttachmentReferenceFilter(new InMemoryAttachmentRepository()));
        self::assertStringNotContainsString('0b6f4f0e', $filtered->body($revision));
        self::assertStringContainsString('<p>viewer: source</p>', $filtered->body($revision));
    }

    public function testTheRenderRunsInAFrameNamingItsSpaceAndPage(): void
    {
        $context  = new WikiRenderContext();
        $seen     = [];
        $renderer = new class($context, $seen) implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface {
            /** @param list<WikiRenderFrame|null> $seen */
            public function __construct(private readonly WikiRenderContext $context, private array &$seen)
            {
            }

            public function render(string $markdown): string
            {
                return 'neutral';
            }

            public function renderForViewer(string $markdown): string
            {
                $this->seen[] = $this->context->current();

                return '<p>' . $markdown . '</p>';
            }
        };

        $body = new WikiRevisionBodyRenderer($renderer, new WikiHtmlSanitizer(), null, $context);

        self::assertSame('<p>source</p>', $body->body(new WikiPageRevision($this->page, 1, '<p>cached</p>', new TestUser(), 'source')));
        self::assertCount(1, $seen);
        self::assertInstanceOf(WikiRenderFrame::class, $seen[0]);
        self::assertSame($this->page, $seen[0]->page);
        self::assertSame($this->page->getSpace(), $seen[0]->space);
        self::assertNull($context->current());
    }

    private function viewerRenderer(): WikiMarkdownRendererInterface
    {
        return new class implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface {
            public function render(string $markdown): string
            {
                return 'neutral';
            }

            public function renderForViewer(string $markdown): string
            {
                return '<p onclick="x()">viewer: ' . $markdown . '</p><a href="/wiki/attachments/0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10">f</a><script>x</script>';
            }
        };
    }
}
