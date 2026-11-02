<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;

/**
 * The body of a revision as the current viewer reads it (1.13, page view).
 *
 * An HTML revision, or any revision when the Markdown renderer is viewer-neutral, shows its stored `content_html`. A
 * Markdown revision rendered by a {@see WikiViewerMarkdownRendererInterface} (the Milkdown bridge) is rendered again
 * for the viewer — page links with the titles of pages they may read, mentions, host blocks — then sanitized and
 * stripped of attachment URLs of other spaces exactly like the stored cache. The result is never stored.
 */
final readonly class WikiRevisionBodyRenderer
{
    /**
     * @param WikiMarkdownRendererInterface $renderer the Markdown renderer of the bundle
     * @param WikiHtmlSanitizerInterface $sanitizer sanitizes the rendered HTML
     * @param WikiAttachmentReferenceFilter|null $attachmentFilter drops attachment URLs of other spaces
     */
    public function __construct(
        private WikiMarkdownRendererInterface $renderer,
        private WikiHtmlSanitizerInterface $sanitizer,
        private ?WikiAttachmentReferenceFilter $attachmentFilter = null,
    ) {
    }

    /**
     * Returns the HTML body of the revision for the current viewer.
     *
     * @param WikiPageRevision $revision revision to show
     *
     * @return string sanitized HTML
     */
    public function body(WikiPageRevision $revision): string
    {
        $markdown = $revision->getContentMarkdown();
        if ($markdown === null || !$this->renderer instanceof WikiViewerMarkdownRendererInterface) {
            return $revision->getContentHtml();
        }

        $html = $this->sanitizer->sanitize($this->renderer->renderForViewer($markdown));

        return $this->attachmentFilter instanceof WikiAttachmentReferenceFilter
            ? $this->attachmentFilter->filter($revision->getPage()->getSpace(), $html)
            : $html;
    }

    /**
     * Whether page bodies carry rich content (Mermaid, math) the dialect's read-side script draws.
     */
    public function hasRichContent(): bool
    {
        return $this->renderer instanceof WikiViewerMarkdownRendererInterface;
    }
}
