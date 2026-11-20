<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Interchange\HtmlToMarkdown;

use League\HTMLToMarkdown\Converter\HeaderConverter;
use League\HTMLToMarkdown\ElementInterface;

use function trigger_deprecation;

/**
 * Headings with the Milkdown dialect alignment (1.13, {@see WikiAlignmentTrait}).
 *
 * @deprecated since 1.40, the dialect converter uses {@see WikiHeadingIdConverter} (alignment and the `{#id}` suffix);
 *             removed in 2.0
 */
final class WikiAlignedHeaderConverter extends HeaderConverter
{
    use WikiAlignmentTrait;

    /** 1.49: the runtime notice of a class 2.0 removes (the bundle no longer uses it). */
    public function __construct()
    {
        trigger_deprecation('nowo-tech/wiki-bundle', '1.49', 'The %s class is deprecated, 2.0 removes it: use %s (alignment and the "{#id}" suffix) instead.', self::class, WikiHeadingIdConverter::class);
    }

    public function convert(ElementInterface $element): string
    {
        return $this->align($element, parent::convert($element));
    }
}
