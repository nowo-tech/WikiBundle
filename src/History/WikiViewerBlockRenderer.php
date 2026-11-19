<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use Nowo\MilkdownEditorBundle\Markdown\MarkdownRendererInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRevisionBodyRenderer;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;

/**
 * 1.45 (`history.rendered_diff`): the editor diff's block renderer for one page — each block goes through the wiki's own
 * per-viewer render (page links with the titles of pages the viewer may read, mentions, host blocks), inside the
 * page's render frame, then the page sanitizer and the attachment filter, exactly as the page view's body
 * ({@see WikiRevisionBodyRenderer}). Made per diff (it holds the page), never a service.
 */
final readonly class WikiViewerBlockRenderer implements MarkdownRendererInterface
{
    public function __construct(
        private WikiViewerMarkdownRendererInterface $renderer,
        private WikiHtmlSanitizerInterface $sanitizer,
        private WikiPage $page,
        private ?WikiRenderContext $renderContext = null,
        private ?WikiAttachmentReferenceFilter $attachmentFilter = null,
    ) {
    }

    public function render(string $markdown): string
    {
        $renderer = $this->renderer;
        $space    = $this->page->getSpace();
        $html     = $this->sanitizer->sanitize($this->renderContext instanceof WikiRenderContext
            ? $this->renderContext->within($space, $this->page, static fn (): string => $renderer->renderForViewer($markdown))
            : $renderer->renderForViewer($markdown));

        return $this->attachmentFilter instanceof WikiAttachmentReferenceFilter ? $this->attachmentFilter->filter($space, $html) : $html;
    }
}
