<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Routing;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\TiptapEditorBundle\NowoTiptapEditorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\KnownValueCsrfTokenManager;
use Nowo\WikiBundle\Tests\Support\RecordingQuotaListener;
use Nowo\WikiBundle\WikiBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * Host-like kernel for REQ-SF-004 rule 7. Its routing is the Symfony default `routing.controllers` resource
 * (what `config/routes.yaml` of a Symfony >= 7.3 skeleton loads) and, only when $importBundleRoutes is true,
 * the documented `type: nowo_wiki` route loader import.
 */
final class RoutingTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $wikiConfig extra nowo_wiki options (1.21 hooks test), with $variant naming its cache
     */
    public function __construct(private readonly bool $importBundleRoutes, private readonly array $wikiConfig = [], private readonly string $variant = '')
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
        return $this->getProjectDir() . '/' . ($this->importBundleRoutes ? 'imported' : 'controllers-only') . $this->variant . '/cache';
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
        if (PHP_VERSION_ID >= 80400) {
            // PHP 8.4+ with Doctrine Bundle 2 (Symfony 7.4 jobs): native lazy objects (default in Doctrine Bundle 3).
            $container->extension('doctrine', ['orm' => ['enable_native_lazy_objects' => true]]);
        }
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_wiki', [
            'user_class' => TestUserEntity::class,
            'security'   => ['allow_unauthenticated' => true],
        ] + $this->wikiConfig);
        if ($this->variant !== '') {
            $container->extension('twig', ['paths' => [__DIR__ . '/templates' => null]]);
            $services = $container->services();
            $services->set('security.csrf.token_manager', KnownValueCsrfTokenManager::class)->public();
            $services->alias(CsrfTokenManagerInterface::class, 'security.csrf.token_manager');
            $services->set(RecordingQuotaListener::class)->tag('kernel.event_listener', ['event' => WikiEvents::QUOTA_CHECK, 'method' => 'onCheck']);
        }
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('routing.controllers');

        if ($this->importBundleRoutes) {
            $routes->import('.', 'nowo_wiki');
        }
    }
}
