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
     * @param string|null $icon 1.38: UX icon name of the page (`page_icon.form_field`), or null for none
     */
    public function __construct(
        public string $title = '',
        public string $content = '',
        public ?string $parentId = null,
        public ?string $icon = null,
    ) {
    }
}
