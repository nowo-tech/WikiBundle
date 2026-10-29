<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security\AttributeSanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

use function strtolower;

/**
 * Keeps a `style` attribute only when it is exactly a Tiptap TextAlign declaration
 * (`text-align: left|center|right|justify`); any other CSS is dropped.
 *
 * @internal
 */
final class TextAlignStyleAttributeSanitizer implements AttributeSanitizerInterface
{
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
     * @return list<string> only `style`
     */
    public function getSupportedAttributes(): array
    {
        return ['style'];
    }

    /**
     * Keeps the `style` value only when it is a single `text-align` declaration, normalized to `text-align: <value>`.
     *
     * @param string $element element name
     * @param string $attribute attribute name
     * @param string $value attribute value
     * @param HtmlSanitizerConfig $config active sanitizer configuration
     *
     * @return string|null the normalized declaration, or null to drop the attribute
     */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (preg_match('/^\s*text-align\s*:\s*(left|center|right|justify)\s*;?\s*$/i', $value, $matches) !== 1) {
            return null;
        }

        return 'text-align: ' . strtolower($matches[1]);
    }
}
