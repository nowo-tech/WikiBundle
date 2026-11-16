<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use InvalidArgumentException;
use Nowo\WikiBundle\Service\WikiPageIconService;

use function in_array;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Validation of the UX icon name of a wiki space (1.32), e.g. {@code tabler:book}. Stateless: pure functions only.
 */
abstract class WikiSpaceIcon
{
    /** Longest icon name stored. */
    public const MAX_LENGTH = 64;

    /** Format `prefix:name`; `D` makes `$` match only at the very end, so a trailing newline is rejected. */
    public const PATTERN = '/^[a-z0-9-]+:[a-z0-9-]+$/D';

    /**
     * 1.39: the pseudo-prefix of emoji values (`emoji:<grapheme>`); accepted only when the allowed prefixes list it
     * (`page_icon.emoji` / `space_icon.emoji`).
     */
    public const EMOJI_PREFIX_NAME = 'emoji';

    /** 1.39: the start of an emoji value. */
    public const EMOJI_PREFIX = 'emoji:';

    /**
     * 1.39: the shape of the grapheme of an emoji value, a cheap guard for every writer (the entity setters included):
     * no ASCII but the keycap bases 0-9 # *, no controls, separators, private use, format characters (bidi, zero-width)
     * or marks, except the ones emoji sequences use (ZWJ, VS15 / VS16, the keycap mark, tag characters). The strict
     * check (exactly one RGI emoji) is the IconSelector catalogue's, in {@see WikiPageIconService}
     * and the form fields.
     */
    public const EMOJI_SHAPE = '/^(?:[0-9#*]|\x{200D}|\x{FE0E}|\x{FE0F}|\x{20E3}|[\x{E0020}-\x{E007F}]|[^\x{00}-\x{7F}\p{Cc}\p{Cf}\p{Co}\p{Cs}\p{Z}\p{M}])+$/Du';

    /** Icon sets accepted unless `space_icon.allowed_prefixes` says otherwise. */
    public const DEFAULT_PREFIXES = ['tabler'];

    /**
     * Tells whether a value is an acceptable icon name.
     *
     * @param list<string> $allowedPrefixes accepted icon set prefixes
     */
    public static function isValid(string $icon, array $allowedPrefixes = self::DEFAULT_PREFIXES): bool
    {
        if (self::isEmoji($icon)) {
            return in_array(self::EMOJI_PREFIX_NAME, $allowedPrefixes, true) && self::emojiOf($icon) !== null;
        }
        if (strlen($icon) > self::MAX_LENGTH || preg_match(self::PATTERN, $icon) !== 1) {
            return false;
        }

        return in_array(explode(':', $icon, 2)[0], $allowedPrefixes, true);
    }

    /**
     * 1.39: whether the value uses the emoji prefix (says nothing about its validity).
     */
    public static function isEmoji(?string $icon): bool
    {
        return $icon !== null && str_starts_with($icon, self::EMOJI_PREFIX);
    }

    /**
     * 1.39: the grapheme of an emoji value of an acceptable shape ({@see self::EMOJI_SHAPE}, at most
     * {@see self::MAX_LENGTH} bytes in all), else null. Used for rendering too: a stored value of another shape is
     * never printed.
     */
    public static function emojiOf(?string $icon): ?string
    {
        if (!self::isEmoji($icon) || strlen((string) $icon) > self::MAX_LENGTH) {
            return null;
        }
        $emoji = substr((string) $icon, strlen(self::EMOJI_PREFIX));

        return $emoji !== '' && preg_match(self::EMOJI_SHAPE, $emoji) === 1 ? $emoji : null;
    }

    /**
     * Throws when a value is not an acceptable icon name.
     *
     * @param list<string> $allowedPrefixes accepted icon set prefixes
     *
     * @throws InvalidArgumentException when the value is invalid
     */
    public static function assertValid(string $icon, array $allowedPrefixes = self::DEFAULT_PREFIXES): void
    {
        if (!self::isValid($icon, $allowedPrefixes)) {
            throw new InvalidArgumentException(sprintf('Invalid space icon "%s": expected "<prefix>:<name>" (max %d characters) with a prefix in [%s].', $icon, self::MAX_LENGTH, implode(', ', $allowedPrefixes)));
        }
    }
}
