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
    public function testSearchKitIsAvailableWhenARecentEnoughOneIsInstalled(): void
    {
        self::assertSame(class_exists(EntitySearchDefinition::class), WikiSearchKitAvailability::isAvailable());
    }

    public function testAnOlderOrMissingSearchKitIsNot(): void
    {
        $all    = static fn (string $name): bool => true;
        $recent = static fn (): string => '1.10.1.0';
        self::assertTrue(WikiSearchKitAvailability::isAvailable($all, $all, $recent));
        // Audit VN-2: 1.9.x and 1.10.0 may mark a full window as exact; an unknown version counts as too old.
        foreach (['1.10.0.0', '1.9.0.0', null, 'dev-feature', '9999999-dev'] as $version) {
            self::assertFalse(WikiSearchKitAvailability::isAvailable($all, $all, static fn (): ?string => $version), (string) $version);
        }
        self::assertNull(WikiSearchKitAvailability::installedVersion('nowo-tech/no-such-package'));
        self::assertNotNull(WikiSearchKitAvailability::installedVersion('symfony/config'));
        foreach (['1.10.1', '1.11.0.0', '2.0.0.0'] as $version) {
            self::assertTrue(WikiSearchKitAvailability::isRecentEnough($version), $version);
        }
        self::assertFalse(WikiSearchKitAvailability::isAvailable(static fn (string $interface): bool => false, $all, $recent));
        // 1.7 had the provider ports but not the recents resolver.
        self::assertFalse(WikiSearchKitAvailability::isAvailable(static fn (string $interface): bool => $interface !== SearchReferenceResolverInterface::class, $all, $recent));
        // 1.8 had every port but not the provider base.
        self::assertFalse(WikiSearchKitAvailability::isAvailable($all, static fn (string $class): bool => $class !== EntitySearchDefinition::class, $recent));
        // 1.9 before audit VF: no SensitiveGroupNotice.
        self::assertFalse(WikiSearchKitAvailability::isAvailable($all, static fn (string $class): bool => $class !== SensitiveGroupNotice::class, $recent));
    }
}
