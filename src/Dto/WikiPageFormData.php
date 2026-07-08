<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Dto;

/**
 * Form payload for creating or editing a wiki page.
 */
final class WikiPageFormData
{
    public function __construct(
        public string $title = '',
        public string $content = '',
        public ?string $parentId = null,
    ) {
    }
}
