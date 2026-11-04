<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security\AttributeSanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

use function in_array;
use function preg_match;

/**
 * Keeps an `id` only when it is one of the anchors the Markdown renderer writes (1.16.2), so page content can never
 * clobber a host element id, a global (`window.location`, `document.forms`…) or `Object.prototype`:
 *
 * - on `h1`…`h6`: a heading id `nowo-h-<slug>` (Milkdown's `HeadingIds` slug: letters, marks and digits joined by
 *   single hyphens, at most 90 characters after the prefix);
 * - on `li`: a footnote `nowo-fn-<8 hex>-<n>`, on `sup` its reference `nowo-fn-ref-<8 hex>-<n>[__<k>]`.
 *
 * Any other value, or an `id` on any other element (including one a host allowed with `extra_allowed_attributes`), is
 * dropped. The value checked is the decoded attribute value, so entity-encoded tricks are seen as what they decode to.
 *
 * @internal
 */
final class AnchorIdAttributeSanitizer implements AttributeSanitizerInterface
{
    /** Prefix of heading ids (the same as nowo-tech/milkdown-editor-bundle `HeadingIds::PREFIX`). */
    public const HEADING_PREFIX = 'nowo-h-';

    /** Prefix of footnote and footnote reference ids. */
    public const FOOTNOTE_PREFIX = 'nowo-fn-';

    private const HEADING_ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

    private const HEADING_PATTERN = '/^nowo-h-(?=.{1,90}$)[\p{L}\p{M}\p{N}]+(?:-[\p{L}\p{M}\p{N}]+)*$/Du';

    private const FOOTNOTE_PATTERN = '/^nowo-fn-[0-9a-f]{8}-[1-9][0-9]{0,5}$/D';

    private const FOOTNOTE_REF_PATTERN = '/^nowo-fn-ref-[0-9a-f]{8}-[1-9][0-9]{0,5}(?:__[1-9][0-9]{0,5})?$/D';

    /**
     * Returns the elements this sanitizer applies to.
     *
     * @return list<string>|null null, meaning every element
     */
    public function getSupportedElements(): ?array
    {
        return null;
    }

    /**
     * Returns the attributes this sanitizer applies to.
     *
     * @return list<string> only `id`
     */
    public function getSupportedAttributes(): array
    {
        return ['id'];
    }

    /**
     * Keeps the id only when it matches the anchor pattern of its element.
     *
     * @param string $element element name
     * @param string $attribute attribute name
     * @param string $value attribute value
     * @param HtmlSanitizerConfig $config active sanitizer configuration
     *
     * @return string|null the id, or null to drop the attribute
     */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $pattern = match (true) {
            in_array($element, self::HEADING_ELEMENTS, true) => self::HEADING_PATTERN,
            $element === 'li'                                => self::FOOTNOTE_PATTERN,
            $element === 'sup'                               => self::FOOTNOTE_REF_PATTERN,
            default                                          => null,
        };

        return $pattern !== null && preg_match($pattern, $value) === 1 ? $value : null;
    }
}
