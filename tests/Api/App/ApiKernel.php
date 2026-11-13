<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\HostFileResolverInterface;
use Nowo\MilkdownEditorBundle\NowoMilkdownEditorBundle;
use Nowo\TiptapEditorBundle\NowoTiptapEditorBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;
use Nowo\WikiBundle\Tests\Support\RecordingQuotaListener;
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

use function array_replace;
use function dirname;
use function md5;
use function serialize;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

use const PHP_VERSION_ID;

/**
 * Host kernel of the API tests: the bundle with `api.enabled`, the Milkdown editor (per-viewer render), and a stateless
 * firewall on the API's prefix. `$withFirewall` false: the host forgot it, and the API must fail closed.
 */
final class ApiKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $api `nowo_wiki.api`, merged over the test host's hooks
     * @param array<string, mixed> $wiki the rest of `nowo_wiki`
     * @param bool $legacySpaceChecker 1.29.1: the bundle's LegacyWikiSpaceAccessChecker decides (not the test host), behind
     *                                 a locator that finds spaces by slug only
     * @param bool $debug false for memory measurements (the debug collectors keep every request until kernel.reset)
     */
    public function __construct(
        private readonly array $api = [],
        private readonly array $wiki = [],
        private readonly bool $withFirewall = true,
        private readonly bool $legacySpaceChecker = false,
        bool $debug = true,
    ) {
        parent::__construct('test', $debug);
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
        return sys_get_temp_dir() . '/nowo-wiki-api';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/' . md5(serialize([$this->api, $this->wiki, $this->withFirewall, $this->legacySpaceChecker, $this->debug, WikiTestDatabase::url(), self::twinPorts()])) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    /** The SQLite file of this configuration (one per cache directory), unless WIKI_TEST_DATABASE_URL names a server. */
    public function databaseUrl(): string
    {
        return WikiTestDatabase::url() ?? 'sqlite:///' . $this->getCacheDir() . '/api.sqlite';
    }

    /** 1.32: whether the API host ports are asked through their framework-free twins (`WIKI_TEST_PORTS=twin`). */
    public static function twinPorts(): bool
    {
        return ($_SERVER['WIKI_TEST_PORTS'] ?? getenv('WIKI_TEST_PORTS')) === 'twin';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'api-test-secret',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'rate_limiter'         => ['wiki_comments' => ['policy' => 'sliding_window', 'limit' => 1000, 'interval' => '1 minute']],
        ]);
        $container->extension('security', [
            'providers' => ['app' => ['entity' => ['class' => TestUserEntity::class, 'property' => 'id']]],
            'firewalls' => ($this->withFirewall ? ['wiki_api' => ['pattern' => '^/api/', 'stateless' => true, 'provider' => 'app', 'custom_authenticators' => ['app.api_firewall']]] : [])
                + ['main' => ['lazy' => true, 'provider' => 'app']],
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => $this->databaseUrl(), 'logging' => true],
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
        $services->set('logger', RecordingLogger::class)->public();
        $services->set('app.api_host', ApiTestHost::class)->autowire()->public();
        // 1.32 (REQ-ARCH-001): WIKI_TEST_PORTS=twin asks every API host port through its framework-free twin.
        $hostPorts = 'app.api_host';
        $filesPort = 'app.api_files';
        if (self::twinPorts()) {
            $services->set('app.api_twin', ApiTwinHost::class)->args([service('app.api_host'), service('doctrine')])->public();
            $services->set('app.api_files_twin', ApiTwinFiles::class)->args([service('app.api_files'), service('doctrine')])->public();
            $hostPorts = 'app.api_twin';
            $filesPort = 'app.api_files_twin';
        }
        $services->set('app.api_firewall', ApiFirewallAuthenticator::class)->args([service('app.api_host')]);
        if ($this->legacySpaceChecker) {
            $services->set('app.slug_locator', ApiSlugLocator::class)->args([service(WikiSpaceRepositoryInterface::class)]);
            $services->alias(WikiSpaceLocatorInterface::class, 'app.slug_locator');
        } else {
            $services->alias(WikiSpaceAccessCheckerInterface::class, $hostPorts);
        }
        $services->set('app.audit', ApiAuditRecorder::class)->public()->args([service('doctrine.dbal.default_connection')])
            ->tag('kernel.event_listener', ['event' => WikiEvents::API_WRITE, 'method' => 'record'])
            ->tag('kernel.event_listener', ['event' => WikiEvents::PAGE_SAVED, 'method' => 'saved'])
            ->tag('kernel.event_listener', ['event' => WikiEvents::COMMENT_CREATED, 'method' => 'commented']);
        $services->set('app.collab_store', ApiCollabStore::class)->args([service(WikiEntityManagerProvider::class)])->public();
        $services->set('app.collab_hub', ApiCollabHub::class)->public();
        $services->set('app.api_files', ApiTestFiles::class)->public();
        // 1.25: files referenced by plain URL (/files/<uuid>/…) resolve through the host's reach-aware resolver.
        $services->set('app.host_files', ApiHostFiles::class)->args([service(WikiEntityManagerProvider::class)]);
        $services->alias(HostFileResolverInterface::class, 'app.host_files');
        $container->extension('nowo_milkdown_editor', ['markdown' => [
            'host_schemes'       => ['beacon-file' => ['file' => '/files/{id}/original', 'image' => '/files/{id}/preview']],
            'host_url_patterns'  => [['pattern' => '^/files/(?<id>[0-9a-f-]{36})/(?:original|preview|thumb|s128|s64)$', 'scheme' => 'beacon-file']],
            'host_url_hosts'     => ['beacon.example'],
            'host_refused_links' => 'hidden',
        ]]);
        $services->set('app.pages', ApiPageRepository::class)->args([service(WikiEntityManagerProvider::class)]);
        $services->alias(WikiPageRepositoryInterface::class, 'app.pages');
        $services->set('app.quota', RecordingQuotaListener::class)->public()
            ->tag('kernel.event_listener', ['event' => WikiEvents::QUOTA_CHECK, 'method' => 'onCheck']);
        $container->extension('nowo_wiki', array_replace([
            'user_class' => TestUserEntity::class,
            'editor'     => ['type' => 'milkdown'],
            'comments'   => ['enabled' => true, 'rate_limiter' => 'limiter.wiki_comments'],
            'history'    => ['timeline' => true],
            'tree'       => ['enabled' => true],
            'security'   => ['access_roles' => ['ROLE_USER']],
            'api'        => $this->api + ['enabled' => true, 'hooks' => [
                'authenticator'   => $hostPorts,
                'directory'       => $hostPorts,
                'rate_limiter'    => $hostPorts,
                'plan_gate'       => $hostPorts,
                'users'           => $hostPorts,
                'wiki_management' => $hostPorts,
                'files'           => $filesPort,
            ]],
        ], $this->wiki));
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'nowo_wiki');
    }
}
