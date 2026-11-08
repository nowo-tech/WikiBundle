<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Service\WikiEventDeferral;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * 1.24 (audit IF-L2): events of a write inside an outer transaction wait for its end, and are dropped when it fails.
 */
final class WikiEventDeferralTest extends TestCase
{
    public function testEventsWaitForTheOutermostScope(): void
    {
        $heard      = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('e', static function () use (&$heard): void {
            $heard[] = 'e';
        });
        $deferral = new WikiEventDeferral();

        $deferral->dispatch($dispatcher, new stdClass(), 'e');
        self::assertSame(['e'], $heard);

        $result = $deferral->run(static function () use ($deferral, $dispatcher, &$heard): string {
            $deferral->run(static fn () => $deferral->dispatch($dispatcher, new stdClass(), 'e'));
            self::assertSame(['e'], $heard);

            return 'done';
        });
        self::assertSame('done', $result);
        self::assertSame(['e', 'e'], $heard);
    }

    public function testAFailureDropsTheHeldEvents(): void
    {
        $heard      = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('e', static function () use (&$heard): void {
            $heard[] = 'e';
        });
        $deferral = new WikiEventDeferral();

        try {
            $deferral->run(static function () use ($deferral, $dispatcher): never {
                try {
                    $deferral->run(static function () use ($deferral, $dispatcher): never {
                        $deferral->dispatch($dispatcher, new stdClass(), 'e');

                        throw new RuntimeException('inner');
                    });
                } catch (RuntimeException) {
                }

                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }
        $deferral->run(static fn () => null);
        self::assertSame([], $heard);

        $deferral->run(static fn () => $deferral->dispatch($dispatcher, new stdClass(), 'e'));
        $deferral->reset();
        self::assertSame(['e'], $heard);
    }

    public function testResetForgetsAnAbortedScope(): void
    {
        $heard      = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('e', static function () use (&$heard): void {
            $heard[] = 'e';
        });
        $deferral = new WikiEventDeferral();
        $deferral->run(static function () use ($deferral, $dispatcher): void {
            $deferral->dispatch($dispatcher, new stdClass(), 'e');
            $deferral->reset();
        });

        $deferral->dispatch($dispatcher, new stdClass(), 'e');
        self::assertSame(['e'], $heard);
    }
}
