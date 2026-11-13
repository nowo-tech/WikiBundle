<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiPortsPass;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Nowo\WikiBundle\Port\WikiAccessPolicyGate;
use Nowo\WikiBundle\Port\WikiPorts;
use Nowo\WikiBundle\Port\WikiSpaceReachGate;
use Nowo\WikiBundle\Repository\WikiSpaceRepositoryInterface;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Service\WikiSpaceIdLocatorInterface;
use Nowo\WikiBundle\Service\WikiSpaceReachInterface;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_map;

final class WikiPortsPassTest extends TestCase
{
    public function testALegacyServiceIsAskedAsBeforeEvenWhenItImplementsTheTwin(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.both', new Definition(BothAccessChecker::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.both');

        (new WikiPortsPass())->process($container);

        self::assertSame('app.both', (string) $container->getAlias(WikiAccessCheckerInterface::class));
    }

    public function testATwinOnlyServiceIsAskedThroughItsGate(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.policy', new Definition(TwinAccessPolicy::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.policy')->setPublic(true);

        (new WikiPortsPass())->process($container);

        $gateId = (string) $container->getAlias(WikiAccessCheckerInterface::class);
        self::assertSame(WikiPortsPass::GATE_PREFIX . 'access_checker', $gateId);
        self::assertTrue($container->getAlias(WikiAccessCheckerInterface::class)->isPublic());
        $gate = $container->getDefinition($gateId);
        self::assertSame(WikiAccessPolicyGate::class, $gate->getClass());
        self::assertEquals([new Reference('app.policy')], $gate->getArguments());
    }

    public function testAGateGetsItsOtherServices(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(WikiSpaceAccessResolverInterface::class, new Definition(TwinSpaceReach::class));

        (new WikiPortsPass())->process($container);

        $gate = $container->getDefinition(WikiPortsPass::GATE_PREFIX . 'space_access_resolver');
        self::assertSame(WikiSpaceReachGate::class, $gate->getClass());
        self::assertEquals([new Reference(WikiPortsPass::GATE_PREFIX . 'space_access_resolver.inner'), new Reference(WikiSpaceRepositoryInterface::class)], $gate->getArguments());
        self::assertSame(TwinSpaceReach::class, $container->getDefinition(WikiPortsPass::GATE_PREFIX . 'space_access_resolver.inner')->getClass());
    }

    public function testAServiceImplementingNeitherFailsTheBuildNamingTheOption(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.wrong', new Definition(stdClass::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.wrong');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nowo_wiki.security.access_checker');
        (new WikiPortsPass())->process($container);
    }

    public function testAFactoryServiceIsCheckedWhenBuilt(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.factory', (new Definition(WikiAccessCheckerInterface::class))->setFactory([TwinAccessPolicy::class, 'create']));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.factory');

        (new WikiPortsPass())->process($container);

        $gate = $container->getDefinition(WikiPortsPass::GATE_PREFIX . 'access_checker');
        self::assertSame([WikiPorts::class, 'port'], $gate->getFactory());
        self::assertEquals(['access_checker', new Reference('app.factory')], $gate->getArguments());

        // A service the build does not define yet (registered later or by another bundle) is checked when built too.
        $later = new ContainerBuilder();
        $later->setAlias(WikiAccessCheckerInterface::class, 'app.later');
        (new WikiPortsPass())->process($later);
        self::assertSame([WikiPorts::class, 'port'], $later->getDefinition(WikiPortsPass::GATE_PREFIX . 'access_checker')->getFactory());
    }

    public function testATwinAliasReplacesTheBundleDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('nowo_wiki.access_checker.allow_all', new Definition(AllowAllWikiAccessChecker::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'nowo_wiki.access_checker.allow_all');
        $container->setDefinition('app.policy', new Definition(TwinAccessPolicy::class));
        $container->setAlias(WikiAccessPolicyInterface::class, 'app.policy');

        (new WikiPortsPass())->process($container);

        $gate = $container->getDefinition((string) $container->getAlias(WikiAccessCheckerInterface::class));
        self::assertEquals([new Reference('app.policy')], $gate->getArguments());
    }

    public function testATwinAliasNextToAHostLegacyServiceFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.legacy', new Definition(BothAccessChecker::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.legacy');
        $container->setDefinition('app.policy', new Definition(TwinAccessPolicy::class));
        $container->setAlias(WikiAccessPolicyInterface::class, 'app.policy');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('keep only one');
        (new WikiPortsPass())->process($container);
    }

    public function testTheSameServiceBehindBothIdsIsAskedThroughTheDeprecatedInterface(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.both', new Definition(BothAccessChecker::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.both');
        $container->setAlias(WikiAccessPolicyInterface::class, 'app.both');

        (new WikiPortsPass())->process($container);

        self::assertSame('app.both', (string) $container->getAlias(WikiAccessCheckerInterface::class));
    }

    public function testObjectLinkResolversMustImplementOneOfTheInterfaces(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.twin_links', (new Definition(TwinLinks::class))->addTag(WikiPortsPass::OBJECT_LINK_TAG));
        $container->setDefinition('app.factory_links', (new Definition(TwinLinks::class))->setFactory([TwinLinks::class, 'create'])->addTag(WikiPortsPass::OBJECT_LINK_TAG));
        (new WikiPortsPass())->process($container);

        $container->setDefinition('app.wrong_links', (new Definition(stdClass::class))->addTag(WikiPortsPass::OBJECT_LINK_TAG));
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(WikiObjectLinkSourceInterface::class);
        (new WikiPortsPass())->process($container);
    }

    public function testThePortFactoryBuildsEveryGate(): void
    {
        foreach (WikiPorts::PORTS as $name => [$legacy, $twin, $gate, $dependencies]) {
            $built = WikiPorts::port($name, $this->createMock($twin), ...array_map(fn (string $class): object => $this->createMock($class), $dependencies));
            self::assertInstanceOf($gate, $built, $name);
            self::assertInstanceOf($legacy, $built, $name);
        }
        $this->expectException(LogicException::class);
        WikiPorts::port('space_locator', $this->createMock(WikiSpaceIdLocatorInterface::class));
    }

    public function testThePortFactoryFailsClosed(): void
    {
        $legacy = new BothAccessChecker();
        self::assertSame($legacy, WikiPorts::port('access_checker', $legacy));
        self::assertInstanceOf(WikiAccessPolicyGate::class, WikiPorts::port('access_checker', new TwinAccessPolicy()));
        $repository = $this->createMock(WikiSpaceRepositoryInterface::class);
        self::assertInstanceOf(WikiSpaceReachGate::class, WikiPorts::port('space_access_resolver', new TwinSpaceReach(), $repository));

        try {
            WikiPorts::port('nope', $legacy);
            self::fail('unknown port');
        } catch (LogicException $exception) {
            self::assertStringContainsString('Unknown WikiBundle port', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nowo_wiki.security.access_checker');
        WikiPorts::port('access_checker', new stdClass());
    }
}

final class TwinAccessPolicy implements WikiAccessPolicyInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function canAccess(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canList(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canCreate(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canEdit(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canViewHistory(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canArchive(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canAskAi(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canImport(?WikiUserRef $user = null): bool
    {
        return true;
    }

    public function canExport(?WikiUserRef $user = null): bool
    {
        return true;
    }
}

/**
 * A deprecated-interface checker that also answers the twin's methods (same names, no conflict at runtime here).
 */
abstract class AllowAllWikiAccessCheckerShim implements WikiAccessCheckerInterface
{
    public function canAccess(mixed $user = null): bool
    {
        return true;
    }

    public function canList(mixed $user = null): bool
    {
        return true;
    }

    public function canCreate(mixed $user = null): bool
    {
        return true;
    }

    public function canEdit(mixed $user = null): bool
    {
        return true;
    }

    public function canViewHistory(mixed $user = null): bool
    {
        return true;
    }

    public function canArchive(mixed $user = null): bool
    {
        return true;
    }

    public function canAskAi(mixed $user = null): bool
    {
        return true;
    }

    public function canImport(mixed $user = null): bool
    {
        return true;
    }

    public function canExport(mixed $user = null): bool
    {
        return true;
    }
}

final class BothAccessChecker extends AllowAllWikiAccessCheckerShim implements WikiAccessPolicyInterface
{
}

final class TwinSpaceReach implements WikiSpaceReachInterface
{
    public function spaceIdsFor(WikiUserRef $user): array
    {
        return [];
    }

    public function canAccessSpace(WikiUserRef $user, WikiSpaceSnapshot $space): bool
    {
        return false;
    }
}

final class TwinLinks implements WikiObjectLinkSourceInterface
{
    public static function create(): self
    {
        return new self();
    }

    public function supports(string $objectType): bool
    {
        return false;
    }

    public function resolveVisible(WikiUserRef $user, string $objectType, array $objectIds): array
    {
        return [];
    }
}
