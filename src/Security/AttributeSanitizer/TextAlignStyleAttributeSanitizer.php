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
    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (preg_match('/^\s*text-align\s*:\s*(left|center|right|justify)\s*;?\s*$/i', $value, $matches) !== 1) {
            return null;
        }

        return 'text-align: ' . strtolower($matches[1]);
    }
}
