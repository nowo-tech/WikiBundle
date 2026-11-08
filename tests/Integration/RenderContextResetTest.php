<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Tests\Integration\Routing\RoutingTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

/**
 * 1.24 (audit IF-I7): the render context is emptied by `kernel.reset` (FrankenPHP worker mode), so a frame an aborted
 * request left behind never reaches the next one.
 */
final class RenderContextResetTest extends TestCase
{
    public function testTheServicesResetterEmptiesTheRenderContext(): void
    {
        $kernel = new RoutingTestKernel(true);
        $kernel->boot();
        try {
            $container = $kernel->getContainer()->get('test.service_container');
            $context   = $container->get(WikiRenderContext::class);
            $resetter  = $container->get('services_resetter');
            self::assertInstanceOf(WikiRenderContext::class, $context);
            self::assertInstanceOf(ResetInterface::class, $resetter);

            $context->within(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), null, static function () use ($context, $resetter): void {
                $resetter->reset();
                self::assertNull($context->current());
            });
        } finally {
            $kernel->shutdown();
        }
    }
}
