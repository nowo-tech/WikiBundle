<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Util;

use InvalidArgumentException;

use function preg_match;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * Normalizes user-facing titles into URL-safe slugs.
 */
final class WikiSlugger
{
    public function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        if ($slug === '') {
            throw new InvalidArgumentException('Slug cannot be empty.');
        }

        return $slug;
    }

    public function isValid(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }
}
