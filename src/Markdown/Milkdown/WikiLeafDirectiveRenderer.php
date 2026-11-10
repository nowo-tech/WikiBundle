<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use InvalidArgumentException;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Nowo\MilkdownEditorBundle\Form\MilkdownEditorType;
use Nowo\MilkdownEditorBundle\Markdown\DialectRenderer;
use Nowo\MilkdownEditorBundle\Markdown\Directive\DirectiveRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\FileCard;
use Nowo\MilkdownEditorBundle\Markdown\Node\LeafDirective;
use Nowo\UiKitBundle\Avatar\AvatarUrlPolicy;
use Symfony\Contracts\Translation\TranslatorInterface;

use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function str_replace;
use function trim;

/**
 * Renders `::name[label]{attributes}` leaf directives for wiki pages (1.16.2). A host
 * {@see DirectiveRendererInterface} that supports the name renders it, as in the Milkdown renderer; otherwise (always
 * in the neutral `content_html`, and for directives no host handles) the card falls back to readable text instead of
 * an empty placeholder:
 *
 * - the text is the label, or `name` followed by its attributes (`beacon · type: issue · id: 42`);
 * - when a `url`, `href` or `link` attribute holds a safe URL it becomes a link: the Milkdown link policy
 *   ({@see DialectRenderer::safeUrl()}) and the UI kit URL policy ({@see AvatarUrlPolicy}: a same-origin `/path` or an
 *   `https://` URL without userinfo, quotes, whitespace or control characters) must both accept it, and a same-origin
 *   path may not hold `//`, `.` / `..` segments or encoded slashes (`/.//host`, `/%2F%2Fhost`). Any other URL
 *   (`javascript:`, `data:`, `http:`, `//host`, entity-encoded schemes…) is dropped;
 * - every value is escaped. `id` and `class` attributes are never copied into the markup.
 *
 * 1.25: a `::file` card whose host file the viewer may not see (Milkdown's `nowo_file_hidden`, set by the host file
 * resolver) renders Milkdown's neutral *Content not visible*, without its name, label or link; so does any directive
 * {@see WikiLeafHostFiles} marked (audit IJ-L2).
 *
 * 1.25 (WK-8, `editor.milkdown.file_cards: card`, viewer environment): a `::file` card the viewer may see reads as
 * Milkdown's own file card ({@see FileCard::render()}: badge, name, size; link / icon / card / preview views), linked to
 * the URL Milkdown's host file resolution gave it (`nowo_file_url`) or its `href`. That URL must also pass this
 * renderer's link policy ({@see self::safeUrl()}); otherwise the card has no link.
 *
 * 1.28 (Symfony Beacon spec 212 block B, `editor.milkdown.file_previews`): a `view="preview"` card frames the preview
 * URL the host's `FilePreviewResolverInterface` gave it (`nowo_file_preview`), when that URL passes the same link
 * policy, in a frame with the `file_preview_sandbox` sandbox, `loading="lazy"` and `referrerpolicy="no-referrer"`. The
 * frame leaves the render as a {@see WikiFilePreviewFrames} placeholder and is put back after the wiki sanitizer. Without
 * the frames service (previews off) or an approved URL, the card reads as before.
 */
final readonly class WikiLeafDirectiveRenderer implements NodeRendererInterface
{
    /** Attributes read as the card's link, in this order. */
    public const URL_ATTRIBUTES = ['url', 'href', 'link'];

    /**
     * A same-origin path that a browser or a host route may turn into `//host` (audit EP-I2): an empty segment, a `.` /
     * `..` segment or an encoded slash / backslash before the query or fragment.
     */
    private const AMBIGUOUS_PATH = '~^(?=/)[^?#]*?(?://|/\\.\\.?(?:/|[?#]|$)|%2f|%5c)~i';

    /** Attributes never shown in the fallback text. */
    private const HIDDEN_ATTRIBUTES = ['id', 'class', ...self::URL_ATTRIBUTES];

    /**
     * @param list<DirectiveRendererInterface> $directiveRenderers host leaf-directive renderers (none in the neutral
     *                                                             environment)
     * @param TranslatorInterface|null $translator 1.25: translates *Content not visible* (Milkdown's `ref.hidden`)
     * @param bool $fileCards 1.25 (WK-8): `::file` cards as Milkdown's file card instead of a plain link
     * @param WikiFilePreviewFrames|null $previewFrames 1.28: keeps approved preview frames past the sanitizer (null: no previews)
     * @param string $previewSandbox 1.28: Milkdown's `file_preview_sandbox` tokens, space separated
     */
    public function __construct(
        private array $directiveRenderers = [],
        private ?TranslatorInterface $translator = null,
        private bool $fileCards = false,
        private ?WikiFilePreviewFrames $previewFrames = null,
        private string $previewSandbox = '',
    ) {
    }

    /**
     * @throws InvalidArgumentException for a node that is not a leaf directive
     */
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof LeafDirective) {
            throw new InvalidArgumentException('Unsupported node ' . $node::class);
        }

        foreach ($this->directiveRenderers as $renderer) {
            if ($renderer->supports($node->name)) {
                return $renderer->render($node);
            }
        }
        $hidden = $this->translator?->trans('ref.hidden', [], MilkdownEditorType::DOMAIN) ?? 'Content not visible';
        if ($node->name === FileCard::NAME && ($node->data->get('nowo_file_hidden', false) === true || $node->data->get(WikiLeafHostFiles::HIDDEN, false) === true)) {
            $node->data->set('nowo_file_hidden', true);

            return FileCard::render($node, ['hidden' => $hidden]);
        }
        if ($this->fileCards && $node->name === FileCard::NAME) {
            $url = $node->data->get('nowo_file_url', null);
            $node->data->set('nowo_file_url', self::safeUrl(is_string($url) ? $url : ($node->attributes['href'] ?? '')) ?? '');
            // 1.28: a preview only from the resolver's URL under the link policy, and only with somewhere to keep the frame.
            $preview = $node->data->get('nowo_file_preview', null);
            $preview = $this->previewFrames instanceof WikiFilePreviewFrames && is_string($preview) ? self::safeUrl($preview) ?? '' : '';
            $node->data->set('nowo_file_preview', $preview);

            $html = FileCard::render($node, [
                'open'     => $this->translator?->trans('file.open', [], MilkdownEditorType::DOMAIN) ?? 'Open',
                'download' => $this->translator?->trans('file.download', [], MilkdownEditorType::DOMAIN) ?? 'Download',
                'hidden'   => $hidden,
                'sandbox'  => $this->previewSandbox,
            ]);

            return $preview === '' ? $html : $this->withFramePlaceholder($node, $html);
        }
        if ($node->data->get(WikiLeafHostFiles::HIDDEN, false) === true) {
            // 1.25 (audit IJ-L2): a directive naming a host file the viewer may not see: no label, attributes or link.
            return '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="' . DialectRenderer::e($node->name) . '"><span class="nowo-md-ref nowo-md-ref--hidden">' . DialectRenderer::e($hidden) . '</span></div>';
        }

        $text = DialectRenderer::e($this->text($node));
        $url  = $this->url($node);
        $body = $url === null
            ? '<span class="nowo-wiki-directive__text">' . $text . '</span>'
            : '<a class="nowo-wiki-directive__link" href="' . DialectRenderer::e($url) . '" rel="nofollow noopener">' . $text . '</a>';

        return '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="' . DialectRenderer::e($node->name) . '">' . $body . '</div>';
    }

    /**
     * 1.28: the preview card with its frame replaced by a {@see WikiFilePreviewFrames} placeholder; the plain card when
     * the frame cannot be kept.
     */
    private function withFramePlaceholder(LeafDirective $node, string $html): string
    {
        if (preg_match('~<iframe class="nowo-md-file__frame"[^>]*></iframe>~', $html, $frame) === 1) {
            $placeholder = $this->previewFrames?->placeholder($frame[0]);
            if ($placeholder !== null) {
                return str_replace($frame[0], $placeholder, $html);
            }
        }
        $node->data->set('nowo_file_preview', '');

        return FileCard::render($node, ['hidden' => $this->translator?->trans('ref.hidden', [], MilkdownEditorType::DOMAIN) ?? 'Content not visible']);
    }

    /**
     * The first safe URL of the card, or null.
     */
    public static function safeUrl(string $url): ?string
    {
        if (DialectRenderer::safeUrl($url) !== $url || preg_match(self::AMBIGUOUS_PATH, $url) === 1) {
            return null;
        }

        return AvatarUrlPolicy::sanitize($url);
    }

    private function url(LeafDirective $node): ?string
    {
        // 1.25 (audit IJ-L2): a `::file` whose `href` is a host file links only that resolved `href`.
        foreach ($node->data->get(WikiLeafHostFiles::HREF_ONLY, false) === true ? ['href'] : self::URL_ATTRIBUTES as $key) {
            $url = isset($node->attributes[$key]) ? self::safeUrl($node->attributes[$key]) : null;
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private function text(LeafDirective $node): string
    {
        $label = trim($node->label);
        if ($label !== '') {
            return $label;
        }

        $parts = [$node->name];
        foreach ($node->attributes as $key => $value) {
            if (!in_array($key, self::HIDDEN_ATTRIBUTES, true)) {
                $parts[] = trim($value) === '' ? $key : $key . ': ' . trim($value);
            }
        }

        return implode(' · ', $parts);
    }
}
