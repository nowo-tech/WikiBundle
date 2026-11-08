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
use function preg_match;
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
 * resolver) renders Milkdown's neutral *Content not visible*, without its name, label or link.
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
     */
    public function __construct(
        private array $directiveRenderers = [],
        private ?TranslatorInterface $translator = null,
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
        if ($node->name === FileCard::NAME && $node->data->get('nowo_file_hidden', false) === true) {
            return FileCard::render($node, ['hidden' => $this->translator?->trans('ref.hidden', [], MilkdownEditorType::DOMAIN) ?? 'Content not visible']);
        }

        $text = DialectRenderer::e($this->text($node));
        $url  = $this->url($node);
        $body = $url === null
            ? '<span class="nowo-wiki-directive__text">' . $text . '</span>'
            : '<a class="nowo-wiki-directive__link" href="' . DialectRenderer::e($url) . '" rel="nofollow noopener">' . $text . '</a>';

        return '<div class="nowo-md-directive nowo-md-directive--leaf nowo-wiki-directive" data-directive="' . DialectRenderer::e($node->name) . '">' . $body . '</div>';
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
        foreach (self::URL_ATTRIBUTES as $key) {
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
