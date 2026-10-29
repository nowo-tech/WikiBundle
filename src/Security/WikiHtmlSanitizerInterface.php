<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

/**
 * Sanitizes rich-text HTML from Tiptap before persistence and rendering.
 */
interface WikiHtmlSanitizerInterface
{
    /**
     * Sanitizes rich-text HTML, keeping only allowlisted elements, attributes and URLs.
     *
     * @param string $html untrusted HTML
     *
     * @return string sanitized HTML
     */
    public function sanitize(string $html): string;
}
