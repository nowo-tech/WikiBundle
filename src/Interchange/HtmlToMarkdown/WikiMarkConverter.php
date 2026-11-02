<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

use function trim;

/**
 * `<mark>` as the Milkdown dialect highlight `:mark[text]` (1.13; the text is already escaped by the text converter; the colour of the dialect's default token — Tiptap
 * colours are not kept by the wiki sanitizer).
 */
final class WikiMarkConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $value = trim($element->getValue());

        return $value === '' ? '' : ':mark[' . $value . ']';
    }

    /**
     * @return list<string>
     */
    public function getSupportedTags(): array
    {
        return ['mark'];
    }
}
