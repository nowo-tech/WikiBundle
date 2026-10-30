<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\ConverterInterface;
use League\HTMLToMarkdown\ElementInterface;

use function trim;

/**
 * `<s>`, `<del>` and `<strike>` as GFM strikethrough (`~~text~~`) (1.11).
 */
final class WikiStrikethroughConverter implements ConverterInterface
{
    public function convert(ElementInterface $element): string
    {
        $value = trim($element->getValue());

        return $value === '' ? '' : '~~' . $value . '~~';
    }

    /**
     * @return list<string>
     */
    public function getSupportedTags(): array
    {
        return ['s', 'del', 'strike'];
    }
}
