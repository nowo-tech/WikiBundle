<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\HeaderConverter;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Headings with the Milkdown dialect alignment (1.13, {@see WikiAlignmentTrait}).
 *
 * @deprecated since 1.40, the dialect converter uses {@see WikiHeadingIdConverter} (alignment and the `{#id}` suffix);
 *             removed in 2.0
 */
final class WikiAlignedHeaderConverter extends HeaderConverter
{
    use WikiAlignmentTrait;

    public function convert(ElementInterface $element): string
    {
        return $this->align($element, parent::convert($element));
    }
}
