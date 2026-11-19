<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\SearchKit;

use PHPUnit\Framework\TestCase;

/**
 * 1.46: stands in for SearchKit's EntitySearchProviderTestCase when SearchKit (>= 1.10.1, PHP >= 8.3) is not installed (the
 * PHP 8.2 floor), so the contract test loads and is skipped instead of breaking the suite.
 */
abstract class SearchKitAbsentTestCase extends TestCase
{
    public function testSearchKitIsInstalled(): void
    {
        self::markTestSkipped('nowo-tech/search-kit-bundle >= 1.10.1 is not installed (PHP >= 8.3).');
    }
}
