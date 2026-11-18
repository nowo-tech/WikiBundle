<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search\SearchKit;

use Nowo\SearchKitBundle\Contract\SearchReferenceResolverInterface;
use Nowo\WikiBundle\Search\SearchKit\WikiSearchKitAvailability;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../Stub/SearchKit/SearchKit.php';

final class WikiSearchKitAvailabilityTest extends TestCase
{
    public function testSearchKit18IsAvailable(): void
    {
        self::assertTrue(WikiSearchKitAvailability::isAvailable());
    }

    public function testAnOlderOrMissingSearchKitIsNot(): void
    {
        self::assertFalse(WikiSearchKitAvailability::isAvailable(static fn (string $interface): bool => false));
        // 1.7 had the provider ports but not the recents resolver.
        self::assertFalse(WikiSearchKitAvailability::isAvailable(static fn (string $interface): bool => $interface !== SearchReferenceResolverInterface::class));
    }
}
