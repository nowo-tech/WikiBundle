<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Interchange\HtmlToMarkdown\WikiHeadingIdConverter;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFilePreviewFrames;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;

use function explode;
use function html_entity_decode;
use function preg_replace;
use function preg_replace_callback;
use function str_starts_with;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * 1.24 (spec 218 FR-013): the Markdown a reader who may not edit the page gets — built **from what that reader's
 * render shows**, never by editing the source, so nothing the per-viewer render hides can come through (Milkdown audit
 * DN-I2: HTML comments, reference definitions, raw link targets, hidden blocks, front matter, ids of pages the reader
 * may not open, directive attributes, host references such as files).
 *
 * The source is rendered for the viewer inside a {@see WikiRenderContext} frame (with the API's reach, if any),
 * sanitized and filtered like the page body, and converted back to Markdown. Links to pages the reader may open come
 * back as `[[wiki:<uuid>]]`; the others are the render's neutral *Content not visible*. Without a viewer renderer the
 * neutral render is used: page links all read *Content not visible* and mentions stay `@handle`. Never stored.
 *
 * 1.40: links to a section come back as `[[wiki:<uuid>#<id>]]` and headings keep a differing id as `{#id}`
 * ({@see WikiHeadingIdConverter}), so the reader's Markdown keeps the anchors the page shows.
 */
final readonly class WikiViewerMarkdownView
{
    /**
     * What a reader gets for a source over the renderer's limits (audit IF-L1): such a source is never parsed, so nothing
     * in it could be resolved or hidden, and showing it would show everything.
     */
    public const TOO_LARGE = '*This content is too large to show here.*';

    /**
     * 1.25 (WK-8): the badge and size of a Milkdown file card (`editor.milkdown.file_cards: card`), left out of the
     * reader's Markdown so a card reads `[name](url)` as with the plain link. 1.28: also the *Open* / *Download* actions
     * of a preview card (audit KG-W-L1; the reader view renders without preview frames anyway).
     */
    private const FILE_CARD_DECORATION = '#<span class="nowo-md-file__(?:badge|size)"[^>]*>[^<]*</span>|<span class="nowo-md-file__actions"[^>]*>.*?</span>#s';

    /** An anchor of sanitized HTML, its `href` captured. */
    private const ANCHOR = '#<a\\b[^>]*?\\shref="([^"]*)"[^>]*>.*?</a>#s';

    public function __construct(
        private WikiMarkdownRendererInterface $renderer,
        private WikiHtmlSanitizerInterface $sanitizer,
        private WikiMarkdownConverter $converter,
        private WikiRenderContext $renderContext,
        private ?WikiAttachmentReferenceFilter $attachmentFilter = null,
        private ?WikiFilePreviewFrames $previewFrames = null,
    ) {
    }

    /**
     * The viewer's Markdown of a source.
     *
     * @param WikiSpace $space space the source belongs to
     * @param WikiPage|null $page page the source belongs to (a revision's or a comment's page)
     * @param string $markdown the stored source
     * @param WikiRenderReach|null $reach how far the render may reach beyond the person's access (the API's *where*)
     */
    public function markdown(WikiSpace $space, ?WikiPage $page, string $markdown, ?WikiRenderReach $reach = null): string
    {
        return $this->view($space, $page, $markdown, $reach)['markdown'];
    }

    /**
     * 1.40: the viewer's Markdown of a source and its sections (`[{id, level, text}]`, {@see WikiHeadingOutline}), both
     * from one render: the sections are the headings that render shows.
     *
     * @param WikiSpace $space space the source belongs to
     * @param WikiPage|null $page page the source belongs to
     * @param string $markdown the stored source
     * @param WikiRenderReach|null $reach how far the render may reach beyond the person's access (the API's *where*)
     *
     * @return array{markdown: string, sections: list<array{id: string, level: int, text: string}>}
     */
    public function view(WikiSpace $space, ?WikiPage $page, string $markdown, ?WikiRenderReach $reach = null): array
    {
        if (trim($markdown) === '') {
            return ['markdown' => '', 'sections' => []];
        }
        if (!$this->isShowable($markdown)) {
            return ['markdown' => self::TOO_LARGE, 'sections' => []];
        }

        $renderer       = $this->renderer;
        [$html, $frame] = $renderer instanceof WikiViewerMarkdownRendererInterface
            ? $this->renderContext->within($space, $page, fn (WikiRenderFrame $frame): array => [$this->previewFrames instanceof WikiFilePreviewFrames ? $this->previewFrames->withoutFrames(static fn (): string => $renderer->renderForViewer($markdown)) : $renderer->renderForViewer($markdown), $frame], $reach)
            : [$renderer->render($markdown), null];

        $html = $this->sanitizer->sanitize($html);
        if ($this->attachmentFilter instanceof WikiAttachmentReferenceFilter) {
            $html = $this->attachmentFilter->filter($space, $html);
        }
        $sections = WikiHeadingOutline::fromHtml($html);
        // 1.40: a heading keeps its id as the `{#id}` suffix only where it differs from its text's slug (owner O1),
        // judged before page links become token text (audit RY-L1: their titles are not heading text).
        if ($this->converter->dialect) {
            $html = WikiHeadingIdConverter::keepDifferentIds($html);
        }
        if ($frame instanceof WikiRenderFrame) {
            $html = $this->pageLinksAsTokens($html, $frame);
        }
        $html = preg_replace(self::FILE_CARD_DECORATION, '', $html) ?? $html;

        return [
            'markdown' => $this->converter->htmlToMarkdown($html),
            'sections' => $sections,
        ];
    }

    /**
     * Whether a source can be turned into a reader's Markdown: false over the renderer's limits, where the renderer would
     * fall back to the source as text (audit IF-L1).
     */
    public function isShowable(string $markdown): bool
    {
        return !$this->renderer instanceof WikiMarkdownLimitsInterface || !$this->renderer->exceedsLimits($markdown);
    }

    /**
     * Turns every link the resolver resolved during this render back into its `[[wiki:<uuid>]]` token (the reader may
     * open that page; the token is what an API client follows). The sanitizer re-serializes the HTML, so anchors are
     * well formed, never nested, with a double-quoted, entity-encoded `href`.
     *
     * 1.40: a link to a section (`<page url>#nowo-h-<id>`, Milkdown 1.18) comes back as `[[wiki:<uuid>#<id>]]`; a link
     * with any other fragment stays an ordinary link.
     */
    private function pageLinksAsTokens(string $html, WikiRenderFrame $frame): string
    {
        return preg_replace_callback(
            self::ANCHOR,
            static function (array $match) use ($frame): string {
                $href   = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $parts  = explode('#', $href, 2);
                $anchor = '';
                if (isset($parts[1])) {
                    $anchor = str_starts_with($parts[1], WikiHeadingAnchor::PREFIX) ? WikiHeadingAnchor::normalise($parts[1]) : '';
                    if ($anchor === '') {
                        return $match[0];
                    }
                }
                $pageId = $frame->pageIdOfLink($parts[0]);

                return $pageId !== null ? '[[wiki:' . $pageId . ($anchor !== '' ? '#' . $anchor : '') . ']]' : $match[0];
            },
            $html,
        ) ?? $html;
    }
}
