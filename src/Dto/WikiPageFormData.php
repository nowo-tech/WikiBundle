<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Dto;

/**
 * Form payload for creating or editing a wiki page.
 */
final class WikiPageFormData
{
    /**
     * Creates the page form payload.
     *
     * @param string $title page title
     * @param string $content page HTML content
     * @param string|null $parentId identifier of the parent page, or null for a root page
     */
    public function __construct(
        public string $title = '',
        public string $content = '',
        public ?string $parentId = null,
    ) {
    }
}
