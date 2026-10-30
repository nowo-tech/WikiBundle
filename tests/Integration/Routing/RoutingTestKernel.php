<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Routing;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\TiptapEditorBundle\NowoTiptapEditorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\WikiBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function sys_get_temp_dir;

/**
 * Host-like kernel for REQ-SF-004 rule 7. Its routing is the Symfony default `routing.controllers` resource
 * (what `config/routes.yaml` of a Symfony >= 7.3 skeleton loads) and, only when $importBundleRoutes is true,
 * the documented `type: nowo_wiki` route loader import.
 */
final class RoutingTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly bool $importBundleRoutes)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoTiptapEditorBundle();
        yield new WikiBundle();
    }

    /** Outside the repository: the kernel would otherwise generate config/reference.php in the package. */
    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/nowo-wiki-routing';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/' . ($this->importBundleRoutes ? 'imported' : 'controllers-only') . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'routing-test-secret',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        $container->extension('security', [
            'providers' => ['users' => ['memory' => null]],
            'firewalls' => ['main' => ['stateless' => true, 'provider' => 'users']],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/routing-test.sqlite'],
            'orm'  => ['mappings' => ['WikiTestStub' => [
                'type'      => 'attribute',
                'is_bundle' => false,
                'dir'       => dirname(__DIR__, 2) . '/Stub',
                'prefix'    => 'Nowo\\WikiBundle\\Tests\\Stub',
            ]]],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_wiki', [
            'user_class' => TestUserEntity::class,
            'security'   => ['allow_unauthenticated' => true],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('routing.controllers');

        if ($this->importBundleRoutes) {
            $routes->import('.', 'nowo_wiki');
        }
    }
}
