<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Deprecation;

use Doctrine\Persistence\ManagerRegistry;
use Error;
use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Api\WikiApiWhere;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiPortsPass;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Doctrine\WikiEntityManager;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Doctrine\WikiHostFlushProbe;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Event\WikiEditorOptionsEvent;
use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;
use Nowo\WikiBundle\Interchange\HtmlToMarkdown\WikiAlignedHeaderConverter;
use Nowo\WikiBundle\Interchange\HtmlToMarkdown\WikiHeadingIdConverter;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Nowo\WikiBundle\Port\WikiPorts;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Transaction\WikiStorageFailureSubscriber;
use Nowo\WikiBundle\ValueObject\WikiUserRef;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_filter;
use function array_map;
use function array_values;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_contains;

use const E_DEPRECATED;
use const E_USER_DEPRECATED;
use const E_USER_WARNING;

/**
 * 1.49 (REQ-ARCH-001 wave 0, REQ-BC-001): every default that flips in 2.0 and every member 2.0 removes triggers a
 * runtime notice when used; what keeps working in 2.0 does not.
 */
final class WikiTwoZeroNoticesTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>, bool}> */
    public static function privateEntityManagerConfigs(): iterable
    {
        yield 'unset' => [[], true];
        yield 'false' => [['database' => ['private_entity_manager' => false]], true];
        yield 'true' => [['database' => ['private_entity_manager' => true]], false];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('privateEntityManagerConfigs')]
    public function testThePrivateEntityManagerNoticeAndTheProbeOnlyWithoutIt(array $config, bool $shared): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        $notices = self::deprecations(static fn () => (new WikiExtension())->load([['user_class' => 'App\\Entity\\User'] + $config], $container));

        $mine = array_values(array_filter($notices, static fn (string $message): bool => str_contains($message, 'private_entity_manager')));
        if (!$shared) {
            self::assertSame([], $mine, 'explicitly true: no notice');
            self::assertFalse($container->hasDefinition(WikiHostFlushProbe::class), 'the private manager never flushes the application\'s: no probe');
            self::assertNull($container->getDefinition(WikiEntityManagerProvider::class)->getArgument('$hostFlushProbe'));

            return;
        }
        self::assertSame([
            'Since nowo-tech/wiki-bundle 1.49: ' . sprintf(WikiExtension::PRIVATE_ENTITY_MANAGER_NOTICE, 'default'),
        ], $mine);
        self::assertStringContainsString('"nowo_wiki: { database: { private_entity_manager: true } }"', $mine[0]);
        $probe = $container->getDefinition(WikiHostFlushProbe::class);
        self::assertEquals(new Reference('nowo_transaction_doctrine.host_flush_probe', ContainerBuilder::NULL_ON_INVALID_REFERENCE), $probe->getArgument('$probe'));
        self::assertSame([['event' => 'onFlush', 'method' => 'onFlush', 'lazy' => true, 'entity_manager' => 'default']], $probe->getTag('doctrine.event_listener'));
        self::assertSame([['method' => 'reset']], $probe->getTag('kernel.reset'));
        self::assertEquals(new Reference(WikiHostFlushProbe::class), $container->getDefinition(WikiEntityManagerProvider::class)->getArgument('$hostFlushProbe'));
        // Reset at every request, message and command boundary too (worker mode with kernel reset off).
        $memos = $container->getDefinition(WikiRequestBoundaryResetter::class)->getArgument('$memos');
        self::assertInstanceOf(IteratorArgument::class, $memos);
        self::assertContains(WikiHostFlushProbe::class, array_map(static fn (mixed $reference): string => (string) $reference, $memos->getValues()));
    }

    public function testAHostServiceOnADeprecatedPortInterfaceIsReportedOnce(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.both', new Definition(HostAccessChecker::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.both');
        $container->setDefinition('app.legacy_links', (new Definition(LegacyLinks::class))->addTag(WikiPortsPass::OBJECT_LINK_TAG));

        $notices = self::deprecations(static fn () => (new WikiPortsPass())->process($container));

        self::assertCount(2, $notices);
        self::assertStringContainsString('The service "app.both" (' . HostAccessChecker::class . ') implements the deprecated ' . WikiAccessCheckerInterface::class . ', which 2.0 removes: implement ' . WikiAccessPolicyInterface::class . ' instead and keep it configured as nowo_wiki.security.access_checker', $notices[0]);
        self::assertStringContainsString('"app.legacy_links"', $notices[1]);
        self::assertStringContainsString(WikiObjectLinkSourceInterface::class, $notices[1]);
    }

    public function testTheBundlesOwnDefaultsAndTwinOnlyServicesAreNotReported(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('nowo_wiki.access_checker.allow_all', new Definition(AllowAllWikiAccessChecker::class));
        $container->setAlias(WikiAccessCheckerInterface::class, 'nowo_wiki.access_checker.allow_all');
        self::assertSame([], self::deprecations(static fn () => (new WikiPortsPass())->process($container)));

        $twin = new ContainerBuilder();
        $twin->setDefinition('app.policy', new Definition(HostAccessPolicy::class));
        $twin->setAlias(WikiAccessCheckerInterface::class, 'app.policy');
        self::assertSame([], self::deprecations(static fn () => (new WikiPortsPass())->process($twin)));
    }

    public function testAFactoryBuiltLegacyServiceIsReportedWhenCreated(): void
    {
        $notices = self::deprecations(static fn () => WikiPorts::port('access_checker', new HostAccessChecker()));
        self::assertCount(1, $notices);
        self::assertStringContainsString(HostAccessChecker::class . ' implements the deprecated ' . WikiAccessCheckerInterface::class, $notices[0]);

        self::assertSame([], self::deprecations(static fn () => WikiPorts::port('access_checker', new HostAccessPolicy())));
        self::assertSame([], self::deprecations(static fn () => WikiPorts::port('access_checker', new AllowAllWikiAccessChecker())));
    }

    public function testReadingTheDeprecatedUserMembersIsReported(): void
    {
        $user      = new TestUser('u-1');
        $space     = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $principal = new WikiApiPrincipal($user, WikiApiLevel::Full, WikiApiWhere::everything(), 't-1', 'Token');
        $quota     = new WikiQuotaCheckEvent(WikiQuotaCheckEvent::PAGE_CREATE, $space, $user);
        $options   = new WikiEditorOptionsEvent($space, null, $user);

        foreach ([$principal, $quota, $options] as $holder) {
            $read    = null;
            $notices = self::deprecations(static function () use ($holder, &$read): void {
                $read = $holder->user;
            });
            self::assertSame($user, $read, $holder::class);
            self::assertSame(['Since nowo-tech/wiki-bundle 1.49: Reading ' . $holder::class . '::$user (a Symfony user) is deprecated, 2.0 removes it: read userRef() (a framework-free WikiUserRef) instead.'], $notices);
            self::assertTrue(isset($holder->user));

            // The replacements are silent.
            self::assertSame([], self::deprecations(static function () use ($holder, $user): void {
                self::assertSame('u-1', $holder->userRef()->id);
                self::assertSame($user, $holder->securityUser());
            }));
        }
    }

    public function testOtherPropertiesBehaveAsBefore(): void
    {
        $user   = new TestUser('u-1');
        $space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $events = [new WikiQuotaCheckEvent(WikiQuotaCheckEvent::PAGE_CREATE, $space, $user), new WikiEditorOptionsEvent($space, null, $user)];

        foreach ([new WikiApiPrincipal($user, WikiApiLevel::Full, WikiApiWhere::everything(), 't-1', 'Token'), ...$events] as $holder) {
            self::assertFalse(isset($holder->nope));
            $warnings = self::errors(E_USER_WARNING, static function () use ($holder): void {
                // @phpstan-ignore-next-line - an undefined property, as before
                self::assertNull($holder->nope);
            });
            self::assertSame(['Undefined property: ' . $holder::class . '::$nope'], $warnings);
            try {
                // @phpstan-ignore-next-line - a private member stays private
                $holder->securityUser;
                self::fail('private');
            } catch (Error $error) {
                self::assertSame('Cannot access private property ' . $holder::class . '::$securityUser', $error->getMessage());
            }
        }

        foreach ($events as $event) {
            self::errors(E_DEPRECATED, static function () use ($event): void {
                // @phpstan-ignore-next-line - a dynamic property, as before (deprecated by PHP itself)
                $event->extra = 'x';
            });
            // @phpstan-ignore-next-line
            self::assertSame('x', $event->extra);

            try {
                // @phpstan-ignore-next-line - the write a 1.48 readonly property refused too
                $event->user = new TestUser('u-2');
                self::fail('read-only');
            } catch (Error $error) {
                self::assertSame('Cannot modify readonly property ' . $event::class . '::$user', $error->getMessage());
            }
            try {
                // @phpstan-ignore-next-line - a private member stays private
                $event->securityUser = new TestUser('u-2');
                self::fail('private');
            } catch (Error $error) {
                self::assertSame('Cannot modify private property ' . $event::class . '::$securityUser', $error->getMessage());
            }
        }
    }

    public function testTheProviderHasNoProbeWithThePrivateManager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $probe    = new WikiHostFlushProbe();

        self::assertNull((new WikiEntityManagerProvider($registry, 'default', new WikiEntityManager($registry), $probe))->hostFlushProbe());
        self::assertSame($probe, (new WikiEntityManagerProvider($registry, 'default', null, $probe))->hostFlushProbe());
    }

    public function testCommittedIsReported(): void
    {
        $notices = self::deprecations(static function (): void {
            self::assertFalse(WikiStorageFailureSubscriber::committed(new RuntimeException('x')));
        });

        self::assertCount(1, $notices);
        self::assertStringContainsString(WikiStorageFailureSubscriber::class . '::committed() is deprecated, 2.0 removes it', $notices[0]);
    }

    public function testTheAlignedHeaderConverterIsReported(): void
    {
        $notices = self::deprecations(static fn () => new WikiAlignedHeaderConverter());

        self::assertCount(1, $notices);
        self::assertStringContainsString(WikiHeadingIdConverter::class, $notices[0]);
    }

    /**
     * @return list<string> the E_USER_DEPRECATED messages `$work` triggered
     */
    private static function deprecations(callable $work): array
    {
        return self::errors(E_USER_DEPRECATED, $work);
    }

    /**
     * @return list<string> the messages of the `$levels` errors `$work` triggered
     */
    private static function errors(int $levels, callable $work): array
    {
        $seen = [];
        set_error_handler(static function (int $level, string $message) use (&$seen): bool {
            $seen[] = $message;

            return true;
        }, $levels);
        try {
            $work();
        } finally {
            restore_error_handler();
        }

        return $seen;
    }
}

final class LegacyLinks implements WikiObjectLinkResolverInterface
{
    public function supports(string $objectType): bool
    {
        return false;
    }

    public function resolveVisible(UserInterface $user, string $objectType, array $objectIds): array
    {
        return [];
    }
}

final class HostAccessChecker implements WikiAccessCheckerInterface
{
    public function canAccess(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canList(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canCreate(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canEdit(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canViewHistory(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canArchive(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canAskAi(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canImport(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canExport(?UserInterface $user = null): bool
    {
        return true;
    }
}

final class HostAccessPolicy implements WikiAccessPolicyInterface
{
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
