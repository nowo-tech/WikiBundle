<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\HeaderConverter;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Headings with the Milkdown dialect alignment (1.13, {@see WikiAlignmentTrait}).
 */
final class WikiAlignedHeaderConverter extends HeaderConverter
{
    use WikiAlignmentTrait;

    public function convert(ElementInterface $element): string
    {
        return $this->align($element, parent::convert($element));
    }
}
