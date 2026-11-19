<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search\SearchKit;

use Nowo\SearchKitBundle\Contract\SearchReferenceResolverInterface;
use Nowo\SearchKitBundle\Provider\Entity\EntitySearchDefinition;
use Nowo\SearchKitBundle\Search\Group\SensitiveGroupNotice;
use Nowo\WikiBundle\Search\SearchKit\WikiSearchKitAvailability;
use PHPUnit\Framework\TestCase;

use function class_exists;

final class WikiSearchKitAvailabilityTest extends TestCase
{
    public function testSearchKit19IsAvailableWhenInstalled(): void
    {
        self::assertSame(class_exists(EntitySearchDefinition::class), WikiSearchKitAvailability::isAvailable());
    }

    public function testAnOlderOrMissingSearchKitIsNot(): void
    {
        $all = static fn (string $name): bool => true;
        self::assertTrue(WikiSearchKitAvailability::isAvailable($all, $all));
        self::assertFalse(WikiSearchKitAvailability::isAvailable(static fn (string $interface): bool => false, $all));
        // 1.7 had the provider ports but not the recents resolver.
        self::assertFalse(WikiSearchKitAvailability::isAvailable(static fn (string $interface): bool => $interface !== SearchReferenceResolverInterface::class, $all));
        // 1.8 had every port but not the provider base.
        self::assertFalse(WikiSearchKitAvailability::isAvailable($all, static fn (string $class): bool => $class !== EntitySearchDefinition::class));
        // 1.9 before audit VF: no SensitiveGroupNotice.
        self::assertFalse(WikiSearchKitAvailability::isAvailable($all, static fn (string $class): bool => $class !== SensitiveGroupNotice::class));
    }
}
