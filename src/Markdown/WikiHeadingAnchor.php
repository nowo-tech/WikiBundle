<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use Normalizer;

use function class_exists;
use function is_string;
use function mb_strlen;
use function mb_strtolower;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * 1.40: the one grammar of heading ids and page-link anchors (contract "section anchors and link picker" §2.3), the
 * same as nowo-tech/milkdown-editor-bundle 1.18 `HeadingIds` (`ANCHOR`, `slug()`, `isValid()`, `normalise()`), kept
 * here so that every editor type (Tiptap, Markdown, Milkdown) and the code that does not load Milkdown (the reference
 * extractor fallback, portable exports, the HTML→Markdown converters, the sanitizer) agree on it. A test runs Milkdown's
 * shared vectors against it when Milkdown is installed.
 *
 * - **Id:** Unicode lower-case / other letters, modifier letters and digits, then marks too, joined by single `-`; 1–90
 *   characters. The patterns are linear (the repeated classes never match `-`).
 * - **DOM id:** {@see self::PREFIX} + id (DOM clobbering guard since 1.16.2). A `nowo-h-` prefix on a token anchor or a
 *   suffix is read as the bare id.
 * - **Slug:** the NFC text (with `intl`), lower-case, runs outside the id classes become `-`, trimmed (a leading mark
 *   too), at most 80 characters, `section` when empty.
 *
 * Stateless (worker-safe).
 */
final class WikiHeadingAnchor
{
    /** Prefix of heading DOM ids (Milkdown `HeadingIds::PREFIX`). */
    public const PREFIX = 'nowo-h-';

    /** Longest slug, in characters. */
    public const MAX_SLUG = 80;

    /** Longest id, in characters (a slug of 80 characters plus `-9999`). */
    public const MAX_ID = 90;

    /** The id grammar (PCRE with `/u`), shared with `[[wiki:<uuid>#<id>]]` and the `{#id}` heading suffix. */
    public const ANCHOR = '[\p{Ll}\p{Lo}\p{Lm}\p{N}][\p{Ll}\p{Lo}\p{Lm}\p{M}\p{N}]*(?:-[\p{Ll}\p{Lo}\p{Lm}\p{M}\p{N}]+)*';

    /**
     * Candidate anchor of a page-link token (`[[wiki:<uuid>#…]]`), checked with {@see self::normalise()} afterwards (the
     * dialect parser's own form: an invalid anchor is dropped and the link stays).
     */
    public const TOKEN_ANCHOR = '[^\]\s#]{1,200}';

    /**
     * Whether `$id` is a valid bare id (no prefix).
     *
     * @param string $id a candidate id
     */
    public static function isValid(string $id): bool
    {
        return $id !== ''
            && strlen($id) <= self::MAX_ID * 4
            && mb_strlen($id, 'UTF-8') <= self::MAX_ID
            && preg_match('/^(?:' . self::ANCHOR . ')$/u', $id) === 1;
    }

    /**
     * The bare id of an untrusted value (a token anchor, a suffix, a DOM id): a leading `nowo-h-` is dropped; a value
     * that is not a valid id gives `''`.
     *
     * @param string $value the value
     */
    public static function normalise(string $value): string
    {
        if (str_starts_with($value, self::PREFIX)) {
            $value = substr($value, strlen(self::PREFIX));
        }

        return self::isValid($value) ? $value : '';
    }

    /**
     * The slug of a heading text (the implicit id before de-duplication).
     *
     * @param string $text the heading's text (text and inline code only)
     */
    public static function slug(string $text): string
    {
        if (class_exists(Normalizer::class)) {
            $normal = Normalizer::normalize($text, Normalizer::FORM_C);
            $text   = is_string($normal) ? $normal : $text;
        }
        $slug = trim((string) preg_replace('/[^\p{Ll}\p{Lo}\p{Lm}\p{M}\p{N}]+/u', '-', mb_strtolower($text, 'UTF-8')), '-');
        $slug = (string) preg_replace('/^[\p{M}-]+/u', '', $slug);
        $slug = trim(mb_substr($slug, 0, self::MAX_SLUG, 'UTF-8'), '-');

        return $slug === '' ? 'section' : $slug;
    }
}
