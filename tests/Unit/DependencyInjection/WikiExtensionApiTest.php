<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiPlanGate;
use Nowo\WikiBundle\Api\Hook\DenyingWikiApiRateLimiter;
use Nowo\WikiBundle\Api\Hook\NullWikiApiUsers;
use Nowo\WikiBundle\Api\Hook\WikiApiAuthenticatorInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiIdempotencyStoreInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiPlanGateInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiRateLimiterInterface;
use Nowo\WikiBundle\Api\Hook\WikiApiUsersInterface;
use Nowo\WikiBundle\Api\Idempotency\DoctrineWikiApiIdempotencyStore;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\Security\WikiApiRequestListener;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Routing\WikiRouteLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_intersect;
use function array_keys;
use function str_starts_with;

/**
 * 1.24 (spec 218): `api.*` registers nothing when off, and refuses to build without its required hooks.
 */
final class WikiExtensionApiTest extends TestCase
{
    public function testOffRegistersNothing(): void
    {
        $container = $this->load([]);

        foreach ($container->getDefinitions() as $id => $definition) {
            self::assertFalse(str_starts_with($id, 'Nowo\\WikiBundle\\Api\\') && !$definition->hasTag('container.excluded'), $id);
        }
        self::assertFalse($container->hasAlias(WikiApiAuthenticatorInterface::class));
        self::assertFalse($container->hasParameter('nowo_wiki.api.prefix'));
        self::assertNull($container->getDefinition(WikiRouteLoader::class)->getArguments()['$apiPrefix'] ?? null);
    }

    public function testOnWiresTheHooksTheDenyingDefaultsAndTheRoutes(): void
    {
        $container = $this->load(['enabled' => true, 'prefix' => '/api/kb', 'hooks' => ['authenticator' => 'app.auth', 'directory' => 'app.dir']]);

        self::assertSame('app.auth', (string) $container->getAlias(WikiApiAuthenticatorInterface::class));
        self::assertSame('app.dir', (string) $container->getAlias(WikiApiDirectoryInterface::class));
        self::assertSame(DenyingWikiApiRateLimiter::class, (string) $container->getAlias(WikiApiRateLimiterInterface::class));
        self::assertSame(DenyingWikiApiPlanGate::class, (string) $container->getAlias(WikiApiPlanGateInterface::class));
        self::assertSame(NullWikiApiUsers::class, (string) $container->getAlias(WikiApiUsersInterface::class));
        self::assertTrue($container->hasDefinition(WikiApiRequestListener::class));
        self::assertTrue($container->getDefinition(WikiApiGuard::class)->isPublic());
        self::assertSame('/api/kb', $container->getDefinition(WikiRouteLoader::class)->getArgument('$apiPrefix'));
        self::assertSame(1048576, $container->getParameter('nowo_wiki.api.max_body_bytes'));

        $hosted = $this->load(['enabled' => true, 'hooks' => ['authenticator' => 'a', 'directory' => 'd', 'rate_limiter' => 'r', 'plan_gate' => 'p', 'users' => 'u']]);
        self::assertSame('r', (string) $hosted->getAlias(WikiApiRateLimiterInterface::class));
        self::assertSame('p', (string) $hosted->getAlias(WikiApiPlanGateInterface::class));
        self::assertSame('u', (string) $hosted->getAlias(WikiApiUsersInterface::class));

        // The bundle's idempotency table only with its own store.
        self::assertTrue($container->getDefinition(WikiMetadataListener::class)->getArgument('$apiIdempotencyEnabled'));
        self::assertSame('wiki_api_idempotency', $container->getDefinition(WikiMetadataListener::class)->getArgument('$apiIdempotencyTableName'));
        $own = $this->load(['enabled' => true, 'hooks' => ['authenticator' => 'a', 'directory' => 'd', 'idempotency_store' => 'app.store']]);
        self::assertSame('app.store', (string) $own->getAlias(WikiApiIdempotencyStoreInterface::class));
        self::assertFalse($own->hasDefinition(DoctrineWikiApiIdempotencyStore::class));
        self::assertSame([], array_intersect(['$apiIdempotencyEnabled'], array_keys($own->getDefinition(WikiMetadataListener::class)->getArguments())));
    }

    public function testTheAuthenticatorIsRequired(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nowo_wiki.api.enabled needs api.hooks.authenticator');
        $this->load(['enabled' => true, 'hooks' => ['directory' => 'app.dir']]);
    }

    public function testTheDirectoryIsRequired(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nowo_wiki.api.enabled needs api.hooks.directory');
        $this->load(['enabled' => true, 'hooks' => ['authenticator' => 'app.auth', 'directory' => '']]);
    }

    public function testAllowUnauthenticatedIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be used with security.allow_unauthenticated');
        $this->load(['enabled' => true, 'hooks' => ['authenticator' => 'a', 'directory' => 'd']], ['allow_unauthenticated' => true]);
    }

    public function testThePrefixMustBeAPath(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['enabled' => true, 'prefix' => 'api/wiki/']);
    }

    /**
     * @param array<string, mixed> $api
     * @param array<string, mixed> $security
     */
    private function load(array $api, array $security = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', true);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User', 'api' => $api] + ($security === [] ? [] : ['security' => $security])], $container);

        return $container;
    }
}
