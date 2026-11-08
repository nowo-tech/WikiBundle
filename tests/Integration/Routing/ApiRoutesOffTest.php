<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Routing;

use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function array_filter;
use function array_keys;
use function dirname;
use function str_starts_with;

/**
 * 1.24 (spec 218): a host that does not turn the API on gets no API route and no API service.
 */
final class ApiRoutesOffTest extends TestCase
{
    public function testNoApiRouteOrServiceWithTheOptionOff(): void
    {
        $kernel = new RoutingTestKernel(true);
        (new Filesystem())->remove(dirname($kernel->getCacheDir()));
        $kernel->boot();
        try {
            $routes = array_keys($kernel->getContainer()->get('router')->getRouteCollection()->all());
            self::assertNotSame([], $routes);
            self::assertSame([], array_filter($routes, static fn (string $name): bool => str_starts_with($name, 'nowo_wiki_api_')));
            self::assertFalse($kernel->getContainer()->has(WikiApiGuard::class));
        } finally {
            $kernel->shutdown();
        }
    }
}
