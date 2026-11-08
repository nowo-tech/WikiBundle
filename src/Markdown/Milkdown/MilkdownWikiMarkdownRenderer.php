<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Parser\MarkdownParser;
use Nowo\MilkdownEditorBundle\Markdown\DialectRenderer;
use Nowo\MilkdownEditorBundle\Markdown\Directive\DirectiveRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\DocumentProcessor;
use Nowo\MilkdownEditorBundle\Markdown\FootnoteGuard;
use Nowo\MilkdownEditorBundle\Markdown\HostSchemes;
use Nowo\MilkdownEditorBundle\Markdown\MilkdownDialectExtension;
use Nowo\MilkdownEditorBundle\Markdown\MilkdownMarkdownRenderer;
use Nowo\MilkdownEditorBundle\Markdown\Node\LeafDirective;
use Nowo\MilkdownEditorBundle\Markdown\Node\Mention;
use Nowo\MilkdownEditorBundle\Markdown\Node\TablerIcon;
use Nowo\MilkdownEditorBundle\Markdown\Node\WikiLink;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\HostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\IconResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\PageResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\RenderContextProviderInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\UserResolverInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownLimitsInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownReferenceParserInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRendererInterface;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiTaskListNormalizer;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownRendererInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_slice;
use function array_values;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function iterator_to_array;
use function nl2br;
use function str_replace;
use function strlen;
use function trim;

/**
 * The Milkdown ↔ wiki bridge renderer (1.13, `nowo_wiki.editor.type: milkdown`): CommonMark + GFM + the
 * nowo-tech/milkdown-editor-bundle dialect (`[[wiki:<uuid>]]` page links, `@handle` mentions, `:tabler-<name>:`
 * icons, directives, `$…$` math, Mermaid fences) + every `nowo_wiki.markdown_extension`, with the Milkdown renderer's
 * safety settings and limits (`nowo_milkdown_editor.markdown`):
 *
 * - raw HTML escaped (or stripped with `html_input: strip`), unsafe links always dropped, remote images turned into
 *   links unless `allow_remote_images`, nesting bounded by `max_nesting_level`;
 * - input over `max_input_length` bytes or with more than `max_inline_markers` inline delimiters is rendered as
 *   escaped text without parsing (the parser-cost bound of the Milkdown SECURITY.md);
 * - at most `max_references` distinct page ids and handles reach each resolver (and the link index).
 *
 * Two environments are built once (worker-safe, no request state): the **neutral** one (no page, user or host
 * directive resolution: page links read *Content not visible*, mentions stay `@handle`) renders the `content_html`
 * cache that every reader, search excerpt and AI context share; the **viewer** one resolves page links, mentions and
 * host leaf directives for the current request ({@see self::renderForViewer()}). Task lists get the wiki markup
 * ({@see WikiTaskListNormalizer}) and icons the sanitizer-safe placeholder of {@see WikiTablerIconRenderer}.
 *
 * 1.16.2 (read view of what Milkdown writes): GFM footnotes (`[^1]`, unless `footnotes: false`; at most
 * `max_footnote_refs` references, as in the Milkdown renderer) with the sanitizer-safe ids of {@see WikiFootnoteIds};
 * heading ids `nowo-h-<slug>` with the editor's slugs ({@see WikiHeadingIds}); table alignment as the
 * `nowo-wiki-align--left|center|right` class; leaf directives no host renders fall back to text or a safe link
 * ({@see WikiLeafDirectiveRenderer}).
 *
 * 1.25: the viewer environment resolves host file references — Milkdown's `host_schemes` and, with Milkdown 1.13's
 * `host_url_patterns`, plain file URLs such as `/files/<uuid>/original` — through the host's
 * `HostFileResolverInterface`, given the render's {@see WikiRenderFrame} (space, page,
 * reach; {@see WikiMilkdownHostFileResolver}). A refused file reads *Content not visible*, without URL, alt text or name.
 * With both keys empty nothing changes. The neutral environment never resolves them.
 *
 * 1.25 (WK-8, `editor.milkdown.file_cards: card`): in the viewer environment a `::file` card no host renderer supports
 * reads as Milkdown's own file card, linked to the URL the host file resolution gave it ({@see WikiLeafDirectiveRenderer}).
 */
final readonly class MilkdownWikiMarkdownRenderer implements WikiMarkdownRendererInterface, WikiViewerMarkdownRendererInterface, WikiMarkdownReferenceParserInterface, WikiMarkdownLimitsInterface
{
    /** 1.16.2: class of an aligned table cell (`:---:` → `nowo-wiki-align--center`). */
    public const ALIGN_CLASS_PREFIX = 'nowo-wiki-align--';

    private MarkdownConverter $neutral;

    private MarkdownConverter $viewer;

    private MarkdownParser $parser;

    private int $maxInputLength;

    private int $maxInlineMarkers;

    private int $maxReferences;

    /**
     * Builds the neutral and the viewer environments.
     *
     * @param array<string, mixed> $options the `nowo_milkdown_editor.markdown` options (html_input, allow_remote_images,
     *                                      math, max_nesting_level, max_input_length, max_inline_markers, max_references,
     *                                      mark_colors, footnotes, max_footnote_refs)
     * @param PageResolverInterface $pages resolves page links for the viewer
     * @param UserResolverInterface $users resolves mentions for the viewer
     * @param IconResolverInterface $icons icon allowlist and sprite
     * @param iterable<DirectiveRendererInterface> $directiveRenderers host leaf-directive renderers (viewer only)
     * @param iterable<ExtensionInterface> $extensions extensions tagged nowo_wiki.markdown_extension
     * @param TranslatorInterface|null $translator translates *Content not visible*
     * @param LoggerInterface|null $logger logs resolver failures (never content)
     * @param HostSchemes|null $hostSchemes 1.25: Milkdown's host schemes and URL patterns (viewer only; null: none)
     * @param HostFileResolverInterface|null $hostFiles 1.25: the host's file resolver (viewer only; null: every host reference is refused)
     * @param RenderContextProviderInterface|null $renderContext 1.25: the render's frame for a contextual file resolver
     * @param bool $fileCards 1.25 (WK-8, `editor.milkdown.file_cards: card`): `::file` cards read as Milkdown's file card
     *                        in the viewer environment (false: a plain link, as 1.24)
     */
    public function __construct(
        array $options,
        PageResolverInterface $pages,
        UserResolverInterface $users,
        IconResolverInterface $icons,
        iterable $directiveRenderers = [],
        iterable $extensions = [],
        ?TranslatorInterface $translator = null,
        ?LoggerInterface $logger = null,
        ?HostSchemes $hostSchemes = null,
        ?HostFileResolverInterface $hostFiles = null,
        ?RenderContextProviderInterface $renderContext = null,
        bool $fileCards = false,
    ) {
        $this->maxInputLength   = $this->intOption($options, 'max_input_length', MilkdownMarkdownRenderer::DEFAULT_MAX_INPUT_LENGTH);
        $this->maxInlineMarkers = $this->intOption($options, 'max_inline_markers', MilkdownMarkdownRenderer::DEFAULT_MAX_INLINE_MARKERS);
        $this->maxReferences    = $this->intOption($options, 'max_references', DocumentProcessor::DEFAULT_MAX_REFERENCES);
        $extensions             = iterator_to_array($extensions, false);

        $neutral       = $this->environment($options, new NullPageResolver(), new NullUserResolver(), $icons, [], $extensions, $translator, $logger);
        $this->neutral = new MarkdownConverter($neutral);
        $this->parser  = new MarkdownParser($neutral);
        $this->viewer  = new MarkdownConverter($this->environment(
            $options,
            $pages,
            $users,
            $icons,
            iterator_to_array($directiveRenderers, false),
            $extensions,
            $translator,
            $logger,
            $hostSchemes ?? new HostSchemes(),
            $hostFiles instanceof HostFileResolverInterface ? new WikiMilkdownHostFileResolver($hostFiles) : null,
            $renderContext,
            $fileCards,
        ));
    }

    public function render(string $markdown): string
    {
        return $this->convert($this->neutral, $markdown);
    }

    public function renderForViewer(string $markdown): string
    {
        return $this->convert($this->viewer, $markdown);
    }

    public function references(string $markdown): array
    {
        $markdown = $this->normalize($markdown);
        if ($markdown === '' || !$this->withinLimits($markdown)) {
            return ['pages' => [], 'mentions' => []];
        }

        $pages    = [];
        $mentions = [];
        foreach ($this->parser->parse($markdown)->iterator() as $node) {
            if ($node instanceof WikiLink) {
                $anchor                                       = $node->anchor !== '' ? $node->anchor : null;
                $pages[$node->pageId . '#' . ($anchor ?? '')] = ['id' => $node->pageId, 'anchor' => $anchor];
            } elseif ($node instanceof Mention) {
                $mentions[$node->handle] = $node->handle;
            }
        }

        return [
            'pages'    => array_slice(array_values($pages), 0, $this->maxReferences),
            'mentions' => array_slice(array_values($mentions), 0, $this->maxReferences),
        ];
    }

    public function exceedsLimits(string $markdown): bool
    {
        return !$this->withinLimits($this->normalize($markdown));
    }

    private function convert(MarkdownConverter $converter, string $markdown): string
    {
        $markdown = $this->normalize($markdown);
        if ($markdown === '') {
            return '';
        }

        if (!$this->withinLimits($markdown)) {
            return '<p>' . nl2br(DialectRenderer::e($markdown), false) . '</p>';
        }

        return trim($converter->convert($markdown)->getContent());
    }

    private function withinLimits(string $markdown): bool
    {
        return strlen($markdown) <= $this->maxInputLength
            && MilkdownMarkdownRenderer::countInlineMarkers($markdown) <= $this->maxInlineMarkers;
    }

    private function normalize(string $markdown): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $markdown));
    }

    /**
     * @param array<string, mixed> $options
     * @param list<DirectiveRendererInterface> $directiveRenderers
     * @param list<ExtensionInterface> $extensions
     * @param HostSchemes $hostSchemes host schemes and URL patterns (empty in the neutral environment)
     * @param bool $fileCards 1.25 (WK-8): `::file` cards as Milkdown's file card (viewer environment only)
     */
    private function environment(
        array $options,
        PageResolverInterface $pages,
        UserResolverInterface $users,
        IconResolverInterface $icons,
        array $directiveRenderers,
        array $extensions,
        ?TranslatorInterface $translator,
        ?LoggerInterface $logger,
        HostSchemes $hostSchemes = new HostSchemes(),
        ?WikiMilkdownHostFileResolver $hostFiles = null,
        ?RenderContextProviderInterface $renderContext = null,
        bool $fileCards = false,
    ): Environment {
        $htmlInput   = $options['html_input'] ?? 'escape';
        $markColors  = $options['mark_colors'] ?? ['yellow'];
        $environment = new Environment([
            'html_input'         => in_array($htmlInput, ['escape', 'strip'], true) ? $htmlInput : 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => $this->intOption($options, 'max_nesting_level', 50),
            // 1.16.2: a class, not the `align` attribute the sanitizer strips (and no inline style: CSP-safe).
            'table' => [
                'alignment_attributes' => [
                    'left'   => ['class' => self::ALIGN_CLASS_PREFIX . 'left'],
                    'center' => ['class' => self::ALIGN_CLASS_PREFIX . 'center'],
                    'right'  => ['class' => self::ALIGN_CLASS_PREFIX . 'right'],
                ],
            ],
            // 1.16.2: the Milkdown classes; ids are renamed by WikiFootnoteIds (sanitizer-safe, never user text).
            // Inline `^[…]` footnotes are not GFM and Milkdown never writes them: they stay text.
            'footnote' => [
                'backref_class'           => 'nowo-md-footnote-backref',
                'container_add_hr'        => true,
                'container_class'         => 'nowo-md-footnotes',
                'enable_inline_footnotes' => false,
                'footnote_class'          => 'nowo-md-footnote',
                'footnote_id_prefix'      => WikiFootnoteIds::FOOTNOTE_PREFIX,
                'ref_class'               => 'nowo-md-footnote-ref',
                'ref_id_prefix'           => WikiFootnoteIds::REF_PREFIX,
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());
        if (!in_array($options['footnotes'] ?? true, [false, 0, '0', 'false', ''], true)) {
            $environment->addExtension(new FootnoteExtension());
            // Before league's own (51): advances by characters, so a non-ASCII label keeps its note's text (EP-I6).
            $environment->addBlockStartParser(new WikiFootnoteStartParser(), 52);
            // Before the footnote listeners (40 … 10): case-insensitive labels and the reference cap (Milkdown CZ-L1).
            $environment->addEventListener(DocumentParsedEvent::class, [new FootnoteGuard($this->intOption($options, 'max_footnote_refs', FootnoteGuard::DEFAULT_MAX_FOOTNOTE_REFS)), 'onDocumentParsed'], 50);
            // After orphans become text (30), before numbering (20).
            $environment->addEventListener(DocumentParsedEvent::class, (new WikiFootnoteIds())->onDocumentParsed(...), 25);
        }
        // Linear and capped (EP-L1); the same slugs as Milkdown's HeadingIds.
        $environment->addEventListener(DocumentParsedEvent::class, (new WikiHeadingIds())->onDocumentParsed(...), 0);
        $environment->addExtension(new MilkdownDialectExtension(
            new DialectRenderer($icons, $directiveRenderers, is_array($markColors) ? array_values(array_filter($markColors, is_string(...))) : ['yellow'], $translator),
            new DocumentProcessor(
                $pages,
                $users,
                $directiveRenderers,
                in_array($options['allow_remote_images'] ?? false, [true, 1, '1', 'true'], true),
                $logger,
                $this->maxReferences,
                $hostSchemes,
                $hostFiles,
                renderContext: $renderContext,
                hideRefusedLinks: ($options['host_refused_links'] ?? 'text') === 'hidden',
            ),
            !in_array($options['math'] ?? true, [false, 0, '0', 'false', ''], true),
        ));
        foreach ($extensions as $extension) {
            $environment->addExtension($extension);
        }

        $environment->addRenderer(TablerIcon::class, new WikiTablerIconRenderer($icons), 10);
        $environment->addRenderer(LeafDirective::class, new WikiLeafDirectiveRenderer($directiveRenderers, $translator, $fileCards), 10);
        if (!$hostSchemes->isEmpty()) {
            // 1.25 (audit IJ-L2): URL attributes of leaf directives that name host files go through the resolver too.
            $environment->addEventListener(DocumentParsedEvent::class, (new WikiLeafHostFiles($hostSchemes, $hostFiles, $renderContext, $directiveRenderers, $this->maxReferences, $logger))->onDocumentParsed(...), -50);
        }
        $environment->addEventListener(DocumentParsedEvent::class, new WikiTaskListNormalizer(), -100);

        return $environment;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function intOption(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? null;
        $value = is_string($value) && ctype_digit($value) ? (int) $value : $value;

        return is_int($value) && $value > 0 ? $value : $default;
    }
}
