<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

use function preg_match;
use function str_replace;

/**
 * An embedded video (`<iframe src="https://…">`, kept by the wiki sanitizer for the embed hosts) as a Markdown link to
 * its URL (1.13, Milkdown dialect: Markdown has no embeds, so the conversion report lists it as approximated).
 */
final class WikiEmbedConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $src = $element->getAttribute('src');
        if (preg_match('#^https://[^\s<>()]+$#', $src) !== 1) {
            return '';
        }

        return '[' . str_replace(['[', ']'], ['\\[', '\\]'], $src) . '](' . $src . ")\n\n";
    }

    /**
     * @return list<string>
     */
    public function getSupportedTags(): array
    {
        return ['iframe'];
    }
}
