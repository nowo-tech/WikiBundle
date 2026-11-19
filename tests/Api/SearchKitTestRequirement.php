<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\SearchKitBundle\NowoSearchKitBundle;
use Nowo\WikiBundle\Search\SearchKit\WikiSearchKitAvailability;

use function class_exists;

/**
 * 1.46: the wiki's SearchKit group needs SearchKitBundle >= 1.10.1 (PHP >= 8.3), an optional package: its tests skip without
 * it (the PHP 8.2 floor) and run in every job that installs it (.github/ci/search-kit.sh).
 */
trait SearchKitTestRequirement
{
    protected static function requireSearchKit(): void
    {
        if (!class_exists(NowoSearchKitBundle::class) || !WikiSearchKitAvailability::isAvailable()) {
            self::markTestSkipped('nowo-tech/search-kit-bundle >= 1.10.1 is not installed (PHP >= 8.3).');
        }
    }
}
