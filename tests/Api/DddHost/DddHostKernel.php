<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\DddHost;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\MilkdownEditorBundle\NowoMilkdownEditorBundle;
use Nowo\TiptapEditorBundle\NowoTiptapEditorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\WikiTestDatabase;
use Nowo\WikiBundle\WikiBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function md5;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * 1.34.0 (REQ-ARCH-001, "both host kinds are tested"): a strict DDD / hexagonal host. Its users are plain PHP objects
 * with no Doctrine mapping (no user table at all), the wiki entities are mapped from the XML files, host users are
 * stored by id, the wiki writes through its private entity manager, and every host port is answered through its
 * framework-free twin. Nothing of the host extends or uses a bundle base class or trait.
 */
final class DddHostKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct()
    {
        parent::__construct('test', false);
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
        yield new NowoMilkdownEditorBundle();
        yield new WikiBundle();
    }

    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . '/nowo-wiki-ddd-host';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/' . md5((string) WikiTestDatabase::url()) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    public function databaseUrl(): string
    {
        return WikiTestDatabase::url() ?? 'sqlite:///' . $this->getCacheDir() . '/ddd.sqlite';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'ddd-test-secret',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        $container->extension('security', [
            'providers' => ['ddd' => ['id' => 'app.users']],
            'firewalls' => ['main' => ['lazy' => true, 'provider' => 'ddd']],
        ]);
        // The host maps nothing of its own: the only Doctrine mapping is the wiki's (XML).
        $container->extension('doctrine', [
            'dbal' => ['url' => $this->databaseUrl()],
            'orm'  => [],
        ]);
        if (PHP_VERSION_ID >= 80400) {
            $container->extension('doctrine', ['orm' => ['enable_native_lazy_objects' => true]]);
        }
        $services = $container->services();
        $services->set('logger', RecordingLogger::class)->public();
        $services->set('app.users', DddUsers::class)->public();
        $services->set('app.policies', DddPolicies::class)->public();
        $services->alias(WikiSpaceAccessCheckerInterface::class, 'app.policies');
        $container->extension('nowo_wiki', [
            'user_class'               => DddUser::class,
            'user_directory'           => 'app.users',
            'team_membership_resolver' => 'app.policies',
            'database'                 => [
                'mapping_type'           => 'xml',
                'user_reference'         => 'id',
                'private_entity_manager' => true,
            ],
            'editor'   => ['type' => 'milkdown'],
            'security' => ['access_checker' => 'app.policies'],
            'history'  => ['timeline' => true],
            'tree'     => ['enabled' => true],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_wiki');
    }
}
