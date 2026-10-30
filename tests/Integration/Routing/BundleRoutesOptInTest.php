<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Routing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

use function array_filter;
use function array_keys;
use function array_values;
use function class_exists;
use function dirname;
use function str_starts_with;

/**
 * REQ-SF-004 rule 7: the bundle routes are opt-in. A host whose routing is only the Symfony default
 * `routing.controllers` resource must not get any wiki route; importing the `nowo_wiki` loader exposes them and the
 * controllers keep their AbstractController container.
 */
final class BundleRoutesOptInTest extends TestCase
{
    private ?KernelInterface $kernel = null;

    public static function setUpBeforeClass(): void
    {
        (new Filesystem())->remove(dirname((new RoutingTestKernel(false))->getCacheDir()));
        (new Filesystem())->remove(dirname((new RoutingTestKernel(true))->getCacheDir()));
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testRoutingControllersResourceDoesNotRegisterBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        self::assertSame([], $this->bundleRouteNames(false), 'Wiki routes must exist only where the host imports the nowo_wiki route loader.');
    }

    public function testImportedRoutesServeTheWikiIndex(): void
    {
        $names = $this->bundleRouteNames(true);
        self::assertContains('nowo_wiki_index', $names);
        self::assertContains('nowo_wiki_attachment_download', $names);

        $testContainer = $this->kernel?->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);
        $entityManager = $testContainer->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $tokenStorage = $testContainer->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $tokenStorage);
        $tokenStorage->setToken(new UsernamePasswordToken(new TestUserEntity(), 'main', ['ROLE_USER']));

        // AbstractController::render() / getUser() need the container.service_subscriber locator.
        $response = $this->kernel?->handle(Request::create('/tools/wiki'));
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('/tools/wiki', (string) $response->getContent());
    }

    /** @return list<string> */
    private function bundleRouteNames(bool $importBundleRoutes): array
    {
        $this->kernel = new RoutingTestKernel($importBundleRoutes);
        $this->kernel->boot();

        $router = $this->kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return array_values(array_filter(
            array_keys($router->getRouteCollection()->all()),
            static fn (string $name): bool => str_starts_with($name, 'nowo_wiki_'),
        ));
    }
}
