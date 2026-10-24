<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Security\AttributeSanitizer\EmbedIframeSrcAttributeSanitizer;
use Nowo\WikiBundle\Security\AttributeSanitizer\TextAlignStyleAttributeSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

use function array_merge;

/**
 * Allowlist-based HTML sanitizer for wiki page bodies (mitigates stored XSS).
 *
 * Built on symfony/html-sanitizer (DOM parsing, per-element attribute allowlist): every attribute
 * not listed below (event handlers, `srcdoc`, arbitrary `style`, ...) is removed, links accept only
 * http/https/mailto (or relative) URLs, media only http/https (or relative), and iframes are kept
 * only with an https `src` on the embed host allowlist.
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

    private HtmlSanitizerInterface $sanitizer;

    /**
     * @param list<string> $allowedEmbedHosts Hosts allowed in `<iframe src="https://…">`
     */
    public function __construct(array $allowedEmbedHosts = self::DEFAULT_EMBED_HOSTS)
    {
        $config = (new HtmlSanitizerConfig())
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(-1)
            ->withAttributeSanitizer(new EmbedIframeSrcAttributeSanitizer($allowedEmbedHosts))
            ->withAttributeSanitizer(new TextAlignStyleAttributeSanitizer());

        foreach (self::ALLOWED_ELEMENTS as $element => $attributes) {
            $config = $config->allowElement($element, array_merge(self::COMMON_ATTRIBUTES, $attributes));
        }
        foreach (self::BLOCKED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        $html = $this->sanitizer->sanitize($html);

        // The sanitizer only drops a rejected iframe `src`; remove iframes left without one.
        // Output is canonical here (double-quoted, entity-encoded attributes), so this match is reliable.
        return preg_replace('#<iframe\b(?![^>]*\ssrc=")[^>]*>.*?</iframe>#is', '', $html) ?? $html;
    }
}
