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
    /**
     * Lowercases and trims the value, replacing every run of non-alphanumeric characters with a single dash.
     *
     * @param string $value user-facing text (e.g. a page title)
     *
     * @throws InvalidArgumentException when the value yields an empty slug
     *
     * @return string URL-safe slug
     */
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

    /**
     * Checks whether the value is a well-formed slug (lowercase alphanumeric segments separated by single dashes).
     *
     * @param string $slug slug to validate
     *
     * @return bool true when the slug is valid
     */
    public function isValid(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }
}
