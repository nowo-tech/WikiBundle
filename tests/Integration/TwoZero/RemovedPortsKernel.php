<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\TwoZero;

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
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * 2.0 (lesson XR-WF-M1): a host-like kernel that autodiscovers one fixture directory (`Fixtures/<name>`) with
 * autowiring and autoconfiguration, as an application's `App\` resource does, through the real `WikiBundle::build()`.
 */
final class RemovedPortsKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $fixture)
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
        return sys_get_temp_dir() . '/nowo-wiki-removed-ports';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/' . $this->fixture . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'removed-ports-secret',
            'test'                 => true,
            'http_method_override' => false,
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);
        $container->extension('security', [
            'providers' => ['users' => ['memory' => null]],
            'firewalls' => ['main' => ['stateless' => true, 'provider' => 'users']],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm'  => ['mappings' => ['WikiTestStub' => [
                'type'      => 'attribute',
                'is_bundle' => false,
                'dir'       => dirname(__DIR__, 2) . '/Stub',
                'prefix'    => 'Nowo\\WikiBundle\\Tests\\Stub',
            ]]],
        ]);
        if (PHP_VERSION_ID >= 80400) {
            $container->extension('doctrine', ['orm' => ['enable_native_lazy_objects' => true]]);
        }
        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->defaults()->autowire()->autoconfigure()
            ->load('Nowo\\WikiBundle\\Tests\\Integration\\TwoZero\\Fixtures\\' . $this->fixture . '\\', __DIR__ . '/Fixtures/' . $this->fixture . '/');
        $container->extension('nowo_wiki', [
            'user_class' => TestUserEntity::class,
            'security'   => ['allow_unauthenticated' => true],
        ]);
    }
}
