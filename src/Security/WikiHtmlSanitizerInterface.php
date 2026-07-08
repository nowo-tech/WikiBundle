<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

/**
 * Sanitizes rich-text HTML from Tiptap before persistence and rendering.
 */
interface WikiHtmlSanitizerInterface
{
    public function sanitize(string $html): string;
}
