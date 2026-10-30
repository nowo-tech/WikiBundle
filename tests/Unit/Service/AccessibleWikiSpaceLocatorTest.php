<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Service\AccessibleWikiSpaceLocator;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;

final class AccessibleWikiSpaceLocatorTest extends TestCase
{
    public function testFindsTheSlugAmongTheUsersSpaces(): void
    {
        $eng      = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't'), $eng]);
        $locator = new AccessibleWikiSpaceLocator($resolver);

        self::assertSame($eng, $locator->locate('eng', new TestUser()));
        self::assertNull($locator->locate('hr', new TestUser()));
    }
}
