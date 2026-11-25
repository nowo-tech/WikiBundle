<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\TwoZero;

use LogicException;
use Nowo\WikiBundle\Api\Hook\WikiApiWikiDirectoryInterface;
use Nowo\WikiBundle\Security\WikiAccessPolicyInterface;
use Nowo\WikiBundle\Security\WikiSpaceAccessPolicyInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function sys_get_temp_dir;

/**
 * 2.0 (REQ-BC-001, lesson XR-WF-M1): an application class that still uses one of the removed 1.x host port interfaces
 * fails the container build with the migration message, through the real bundle build, instead of being dropped by
 * Symfony as an unused definition (which would silently widen access or lose a host port).
 */
final class RemovedPortsKernelTest extends TestCase
{
    protected function setUp(): void
    {
        (new Filesystem())->remove(sys_get_temp_dir() . '/nowo-wiki-removed-ports');
    }

    /** @return iterable<string, array{string, list<string>}> */
    public static function refusedFixtures(): iterable
    {
        yield 'AsDecorator of a removed id, implementing it' => ['AsDecoratorRemoved', ['Nowo\\WikiBundle\\Security\\WikiSpaceAccessCheckerInterface', WikiSpaceAccessPolicyInterface::class, 'UPGRADING "To 2.0.0"']];
        yield 'AsDecorator of a removed id, onInvalid IGNORE' => ['DecoratorIgnore', ['decorates "Nowo\\WikiBundle\\Security\\WikiAccessCheckerInterface"', WikiAccessPolicyInterface::class]];
        yield 'AsAlias of a removed id' => ['AsAliasRemoved', ['registered as "Nowo\\WikiBundle\\Security\\WikiAccessCheckerInterface"', 'alias Nowo\\WikiBundle\\Security\\WikiAccessPolicyInterface']];
        yield 'implements a removed interface' => ['ImplementsRemoved', ['HostApiDirectory', 'Nowo\\WikiBundle\\Api\\Hook\\WikiApiDirectoryInterface', WikiApiWikiDirectoryInterface::class]];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('refusedFixtures')]
    public function testARemovedPortFailsTheBuild(string $fixture, array $expected): void
    {
        try {
            (new RemovedPortsKernel($fixture))->boot();
            self::fail('The container must not compile.');
        } catch (LogicException $exception) {
            foreach ($expected as $text) {
                self::assertStringContainsString($text, $exception->getMessage());
            }
            self::assertStringContainsString('2.0 removed', $exception->getMessage());
        }
    }

    public function testAnUnrelatedMissingInterfaceStillCompiles(): void
    {
        $kernel = new RemovedPortsKernel('UnrelatedMissing');
        $kernel->boot();

        self::assertTrue($kernel->getContainer()->has('kernel'));
        $kernel->shutdown();
    }
}
