<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use InvalidArgumentException;
use Nowo\WikiBundle\Security\AttributeSanitizer\EmbedIframeSrcAttributeSanitizer;
use Nowo\WikiBundle\Security\AttributeSanitizer\TextAlignStyleAttributeSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

use function array_diff;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function get_debug_type;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strtolower;

/**
 * Allowlist-based HTML sanitizer for wiki page bodies (mitigates stored XSS).
 *
 * Built on symfony/html-sanitizer (DOM parsing, per-element attribute allowlist): every attribute
 * not listed below (event handlers, `srcdoc`, arbitrary `style`, ...) is removed, links accept only
 * http/https/mailto (or relative) URLs, media only http/https (or relative), and iframes are kept
 * only with an https `src` on the embed host allowlist.
 *
 * 1.11: hosts (and editor bundles) may allow more elements and attributes (`nowo_wiki.sanitizer`), e.g. `<details>`
 * / `<summary>` or the `data-*` attributes of a Markdown dialect. {@see self::assertSafeAllowlist()} refuses anything
 * that could run script or load content: script-capable or embedding elements, `style`, `on*` event handlers and URL
 * attributes stay refused even when configured.
 */
final readonly class WikiHtmlSanitizer implements WikiHtmlSanitizerInterface
{
    /** @var list<string> */
    public const DEFAULT_EMBED_HOSTS = [
        'www.youtube.com',
        'youtube.com',
        'www.youtube-nocookie.com',
        'player.vimeo.com',
    ];

    /** Global attributes allowed on every allowlisted element. */
    private const COMMON_ATTRIBUTES = ['class', 'dir', 'lang', 'title'];

    /** @var array<string, list<string>> element => extra allowed attributes */
    private const ALLOWED_ELEMENTS = [
        'p'          => ['style'],
        'br'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        's'          => [],
        'del'        => [],
        'h1'         => ['style'],
        'h2'         => ['style'],
        'h3'         => ['style'],
        'h4'         => ['style'],
        'h5'         => ['style'],
        'h6'         => ['style'],
        'ul'         => ['data-type'],
        'ol'         => ['start', 'type'],
        'li'         => ['data-type', 'data-checked'],
        'blockquote' => [],
        'code'       => [],
        'pre'        => [],
        'a'          => ['href', 'target', 'rel'],
        'img'        => ['src', 'alt', 'width', 'height'],
        'table'      => [],
        'thead'      => [],
        'tbody'      => [],
        'tr'         => [],
        'th'         => ['colspan', 'rowspan', 'colwidth', 'style'],
        'td'         => ['colspan', 'rowspan', 'colwidth', 'style'],
        'caption'    => [],
        'hr'         => [],
        'span'       => [],
        'div'        => [],
        'figure'     => [],
        'figcaption' => [],
        'sub'        => [],
        'sup'        => [],
        'mark'       => [],
        'iframe'     => ['src', 'width', 'height', 'allowfullscreen', 'frameborder'],
    ];

    /** Harmless wrappers that are removed while their text content is kept (like strip_tags did). */
    private const BLOCKED_ELEMENTS = [
        'abbr', 'address', 'article', 'aside', 'big', 'center', 'cite', 'dd', 'dfn', 'dl', 'dt', 'font',
        'footer', 'header', 'ins', 'kbd', 'label', 'main', 'nav', 'q', 'samp', 'section', 'small', 'time', 'tfoot', 'var',
    ];

    /** Elements that can never be added to the allowlist (script, styles, embedding, forms, foreign content). */
    public const FORBIDDEN_ELEMENTS = [
        'applet', 'base', 'basefont', 'button', 'embed', 'form', 'frame', 'frameset', 'iframe', 'input', 'link',
        'math', 'meta', 'noembed', 'noframes', 'noscript', 'object', 'option', 'param', 'portal', 'script', 'select',
        'style', 'svg', 'template', 'textarea', 'title', 'xmp', 'plaintext', 'html', 'head', 'body',
    ];

    /** Attributes that can never be added (styles, URLs, script hooks); `on*` handlers are refused too. */
    public const FORBIDDEN_ATTRIBUTES = [
        'style', 'href', 'src', 'srcset', 'srcdoc', 'action', 'formaction', 'background', 'poster', 'data', 'codebase',
        'cite', 'longdesc', 'manifest', 'ping', 'xmlns', 'is', 'slot', 'autofocus', 'contenteditable', 'nonce',
    ];

    private HtmlSanitizerInterface $sanitizer;

    /**
     * Builds the underlying symfony/html-sanitizer instance from the element allowlist and attribute sanitizers.
     *
     * @param list<string> $allowedEmbedHosts Hosts allowed in `<iframe src="https://…">`
     * @param array<string, list<string>> $extraAllowedElements extra element => its extra attributes (1.11)
     * @param list<string> $extraAllowedAttributes extra attributes allowed on every allowed element (1.11)
     *
     * @throws InvalidArgumentException when an extra element or attribute is forbidden or not a valid name
     */
    public function __construct(
        array $allowedEmbedHosts = self::DEFAULT_EMBED_HOSTS,
        array $extraAllowedElements = [],
        array $extraAllowedAttributes = [],
    ) {
        self::assertSafeAllowlist($extraAllowedElements, $extraAllowedAttributes);

        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(-1)
            ->withAttributeSanitizer(new EmbedIframeSrcAttributeSanitizer($allowedEmbedHosts))
            ->withAttributeSanitizer(new TextAlignStyleAttributeSanitizer());

        $elements = self::ALLOWED_ELEMENTS;
        foreach ($extraAllowedElements as $element => $attributes) {
            $element            = strtolower($element);
            $elements[$element] = array_merge($elements[$element] ?? [], $attributes);
        }

        foreach ($elements as $element => $attributes) {
            $config = $config->allowElement($element, array_values(array_unique(array_merge(self::COMMON_ATTRIBUTES, $extraAllowedAttributes, $attributes))));
        }
        foreach (array_diff(self::BLOCKED_ELEMENTS, array_keys($elements)) as $element) {
            $config = $config->blockElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Refuses extra elements / attributes that could run script, load content or restyle the page (1.11).
     *
     * @param mixed $elements element => list of attribute names
     * @param mixed $attributes list of attribute names
     *
     * @throws InvalidArgumentException naming the first refused entry
     */
    public static function assertSafeAllowlist(mixed $elements, mixed $attributes): void
    {
        if (!is_array($elements) || !is_array($attributes)) {
            throw new InvalidArgumentException('The extra sanitizer allowlist must be a map of elements and a list of attributes.');
        }

        foreach ($elements as $element => $elementAttributes) {
            if (!is_string($element) || preg_match('/^[a-z][a-z0-9-]*$/i', $element) !== 1 || in_array(strtolower($element), self::FORBIDDEN_ELEMENTS, true)) {
                throw new InvalidArgumentException(sprintf('The element "%s" cannot be allowed by the wiki sanitizer.', (string) $element));
            }

            self::assertSafeAttributes(is_array($elementAttributes) ? $elementAttributes : [$elementAttributes]);
        }

        self::assertSafeAttributes($attributes);
    }

    /**
     * @param array<mixed> $attributes
     */
    private static function assertSafeAttributes(array $attributes): void
    {
        foreach ($attributes as $attribute) {
            $name = is_string($attribute) ? strtolower($attribute) : '';
            if (
                preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1
                || str_starts_with($name, 'on')
                || in_array($name, self::FORBIDDEN_ATTRIBUTES, true)
            ) {
                throw new InvalidArgumentException(sprintf('The attribute "%s" cannot be allowed by the wiki sanitizer.', is_string($attribute) ? $attribute : get_debug_type($attribute)));
            }
        }
    }

    /**
     * Sanitizes rich-text HTML, keeping only allowlisted elements, attributes and URLs.
     * Iframes whose `src` was rejected are removed entirely.
     *
     * @param string $html untrusted HTML
     *
     * @return string sanitized HTML
     */
    public function sanitize(string $html): string
    {
        $html = $this->sanitizer->sanitize($html);

        // The sanitizer only drops a rejected iframe `src`; remove iframes left without one.
        // Output is canonical here (double-quoted, entity-encoded attributes), so this match is reliable.
        return preg_replace('#<iframe\b(?![^>]*\ssrc=")[^>]*>.*?</iframe>#is', '', $html) ?? $html;
    }
}
