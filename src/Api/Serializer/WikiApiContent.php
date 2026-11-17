<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Serializer;

use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Markdown\WikiHeadingOutline;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownView;
use Nowo\WikiBundle\Search\WikiPlainText;

/**
 * 1.24 (spec 218 FR-012 – FR-014): the body of a page, a revision or a comment as this token sees it. The source only
 * when the level and the person allow editing the page; otherwise the reader's Markdown, derived from their render with
 * the token's reach ({@see WikiViewerMarkdownView}). An HTML revision has no Markdown: its plain text.
 *
 * 1.40 (owner decision O6): the page body also lists its `sections`, in the same view as its content.
 */
final readonly class WikiApiContent
{
    public const SOURCE = 'source';

    public const VIEWER = 'viewer';

    public function __construct(
        private WikiApiGuard $guard,
        private WikiViewerMarkdownView $view,
    ) {
    }

    /** Whether this token gets sources on this page (decided once per answer). */
    public function view(WikiApiPrincipal $principal, WikiPage $page): string
    {
        return $this->guard->maySeeSource($principal, $page->getSpace(), $page) ? self::SOURCE : self::VIEWER;
    }

    /**
     * @param bool $sections 1.40: also `sections` (`[{id, level, text}]`, the page body): from the source's render for the
     *                       source view, from the reader's render (with the token's reach) for the viewer view
     *
     * @return array{format: string, content_view: string, content_markdown: string|null, content_text?: string, sections?: list<array{id: string, level: int, text: string}>}
     */
    public function revision(WikiApiPrincipal $principal, WikiPage $page, WikiPageRevision $revision, string $view, bool $sections = false): array
    {
        $markdown = $revision->getContentMarkdown();
        if ($markdown === null) {
            return [
                'format'           => WikiPageRevision::FORMAT_HTML,
                'content_view'     => $view,
                'content_markdown' => null,
                'content_text'     => $revision->getContentText() ?? WikiPlainText::fromHtml($revision->getContentHtml()),
            ] + ($sections ? ['sections' => WikiHeadingOutline::fromHtml($revision->getContentHtml())] : []);
        }
        if ($view === self::SOURCE) {
            // The source's own render (the viewer-neutral cache): every heading of the source, page titles left out.
            return [
                'format'           => WikiPageRevision::FORMAT_MARKDOWN,
                'content_view'     => $view,
                'content_markdown' => $markdown,
            ] + ($sections ? ['sections' => WikiHeadingOutline::fromHtml($revision->getContentHtml())] : []);
        }
        $reader = $this->view->view($page->getSpace(), $page, $markdown, $this->guard->reach($principal));

        return [
            'format'           => WikiPageRevision::FORMAT_MARKDOWN,
            'content_view'     => $view,
            'content_markdown' => $reader['markdown'],
        ] + ($sections ? ['sections' => $reader['sections']] : []);
    }

    /** A Markdown body (a revision's or a comment's) in the given view. */
    public function markdown(WikiApiPrincipal $principal, WikiPage $page, string $markdown, string $view): string
    {
        return $view === self::SOURCE ? $markdown : $this->view->markdown($page->getSpace(), $page, $markdown, $this->guard->reach($principal));
    }
}
