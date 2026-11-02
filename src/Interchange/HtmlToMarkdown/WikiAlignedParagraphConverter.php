<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\ParagraphConverter;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Paragraphs with the Milkdown dialect alignment (1.13, {@see WikiAlignmentTrait}).
 */
final class WikiAlignedParagraphConverter extends ParagraphConverter
{
    use WikiAlignmentTrait;

    public function convert(ElementInterface $element): string
    {
        return $this->align($element, parent::convert($element));
    }
}
