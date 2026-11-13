<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use InvalidArgumentException;

use function in_array;
use function sprintf;
use function strlen;

/**
 * Validation of the UX icon name of a wiki space (1.32), e.g. {@code tabler:book}. Stateless: pure functions only.
 */
final class WikiSpaceIcon
{
    /** Longest icon name stored. */
    public const MAX_LENGTH = 64;

    /** Format `prefix:name`; `D` makes `$` match only at the very end, so a trailing newline is rejected. */
    public const PATTERN = '/^[a-z0-9-]+:[a-z0-9-]+$/D';

    /** Icon sets accepted unless `space_icon.allowed_prefixes` says otherwise. */
    public const DEFAULT_PREFIXES = ['tabler'];

    private function __construct()
    {
    }

    /**
     * Tells whether a value is an acceptable icon name.
     *
     * @param list<string> $allowedPrefixes accepted icon set prefixes
     */
    public static function isValid(string $icon, array $allowedPrefixes = self::DEFAULT_PREFIXES): bool
    {
        if (strlen($icon) > self::MAX_LENGTH || preg_match(self::PATTERN, $icon) !== 1) {
            return false;
        }

        return in_array(explode(':', $icon, 2)[0], $allowedPrefixes, true);
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
