<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use Nowo\MilkdownEditorBundle\Diff\MarkdownDiff;
use Nowo\MilkdownEditorBundle\Diff\MarkdownDiffRenderer;
use Nowo\MilkdownEditorBundle\Markdown\MilkdownMarkdownRenderer;
use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function count;
use function str_replace;

/**
 * 1.45 (`history.rendered_diff`, `editor.type: milkdown`): the rendered diff of two Markdown revisions, with the
 * editor's diff ({@see MarkdownDiffRenderer}, MilkdownEditorBundle >= 1.6): top-level blocks matched, a changed
 * paragraph diffed word by word, deleted text in `<del>`, inserted text in `<ins>`.
 *
 * No stored XSS through a revision: every block is rendered for the viewer and sanitized like the page view's body
 * ({@see WikiViewerBlockRenderer}); then the whole diff goes through a second sanitizer, the page's allow-list plus
 * only the diff's own markup ({@see self::DIFF_ELEMENTS}, {@see self::DIFF_ATTRIBUTES}), so nothing the diff's
 * recombination of tokens could produce runs script or loads content. Rendered on every call, never stored or cached.
 * An HTML revision on either side, or more than {@see self::DEFAULT_MAX_BLOCKS} top-level blocks on either side, returns
 * null (the caller keeps the line diff).
 */
final readonly class WikiRenderedRevisionDiff
{
    /** Elements of the diff markup kept on top of the page allow-list (`<ins>` is otherwise unwrapped). */
    public const DIFF_ELEMENTS = ['ins' => [], 'div' => ['data-change', 'data-nowo-milkdown-diff', 'data-previous', 'data-next', 'data-position']];

    /**
     * Top-level blocks per side at most (audit VC-6): every block is rendered for the viewer on its own (its page links,
     * mentions and host blocks resolved again), so beyond this the Compare page keeps the line diff.
     */
    public const DEFAULT_MAX_BLOCKS = 300;

    /** Attributes of the diff markup (none beyond the elements' own). */
    public const DIFF_ATTRIBUTES = [];

    private WikiHtmlSanitizerInterface $diffSanitizer;

    /**
     * @param WikiMarkdownRendererInterface $renderer the wiki's renderer; a viewer renderer (the Milkdown bridge) is required
     * @param WikiHtmlSanitizerInterface $sanitizer the page sanitizer (each block)
     * @param array<string, list<string>> $extraAllowedElements the page sanitizer's extra elements (`sanitizer` + dialect)
     * @param list<string> $extraAllowedAttributes the page sanitizer's extra attributes
     * @param int $maxInputLength inputs longer than this (bytes) get the editor's escaped line fallback
     */
    public function __construct(
        private WikiMarkdownRendererInterface $renderer,
        private WikiHtmlSanitizerInterface $sanitizer,
        array $extraAllowedElements = [],
        array $extraAllowedAttributes = [],
        private ?WikiRenderContext $renderContext = null,
        private ?WikiAttachmentReferenceFilter $attachmentFilter = null,
        private ?TranslatorInterface $translator = null,
        private int $maxInputLength = MilkdownMarkdownRenderer::DEFAULT_MAX_INPUT_LENGTH,
        private int $maxBlocks = self::DEFAULT_MAX_BLOCKS,
    ) {
        $elements = $extraAllowedElements;
        foreach (self::DIFF_ELEMENTS as $element => $attributes) {
            $elements[$element] = [...($elements[$element] ?? []), ...$attributes];
        }
        $this->diffSanitizer = new WikiHtmlSanitizer(WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS, $elements, [...$extraAllowedAttributes, ...self::DIFF_ATTRIBUTES]);
    }

    /**
     * The diff from `$from` to `$to`, or null when either is not a Markdown revision, either has more than `$maxBlocks`
     * top-level blocks (the caller keeps the line diff), or the renderer is not a viewer renderer.
     */
    public function diff(WikiPageRevision $from, WikiPageRevision $to): ?MarkdownDiff
    {
        if (!$from->isMarkdown() || !$to->isMarkdown() || !$this->renderer instanceof WikiViewerMarkdownRendererInterface) {
            return null;
        }
        foreach ([$from, $to] as $revision) {
            if (count(MarkdownDiffRenderer::blocks(str_replace(["\r\n", "\r"], "\n", (string) $revision->getContentMarkdown()))) > $this->maxBlocks) {
                return null;
            }
        }

        $blocks = new WikiViewerBlockRenderer($this->renderer, $this->sanitizer, $to->getPage(), $this->renderContext, $this->attachmentFilter);
        $diff   = (new MarkdownDiffRenderer($blocks, $this->translator, $this->maxInputLength))->render((string) $from->getContentMarkdown(), (string) $to->getContentMarkdown());

        return new MarkdownDiff($this->diffSanitizer->sanitize($diff->html), $diff->changes, $diff->fallback);
    }
}
