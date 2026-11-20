<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search;

use Nowo\WikiBundle\Search\WikiSearchTenantResolverInterface;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverAdapter;
use Nowo\WikiBundle\Search\WikiSearchTenantsResolverInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

final class WikiSearchTenantsResolverAdapterTest extends TestCase
{
    public function testAListResolverAnswersItself(): void
    {
        $inner = $this->createStub(WikiSearchTenantsResolverInterface::class);
        $inner->method('currentTenants')->willReturn(['a', 'b']);

        self::assertSame(['a', 'b'], (new WikiSearchTenantsResolverAdapter($inner))->currentTenants('u'));
    }

    public function testASingleKeyResolverAnswersAListOfOne(): void
    {
        $inner = $this->createStub(WikiSearchTenantResolverInterface::class);
        $inner->method('currentTenant')->willReturn('a');

        self::assertSame(['a'], (new WikiSearchTenantsResolverAdapter($inner))->currentTenants('u'));
    }

    public function testASingleKeyResolverWithoutTenantAnswersNone(): void
    {
        $inner = $this->createStub(WikiSearchTenantResolverInterface::class);
        $inner->method('currentTenant')->willReturn(null);

        self::assertSame([], (new WikiSearchTenantsResolverAdapter($inner))->currentTenants('u'));
    }

    public function testAnythingElseAnswersNone(): void
    {
        self::assertSame([], (new WikiSearchTenantsResolverAdapter(new stdClass()))->currentTenants('u'));
    }
}
