<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Service;

use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Tests\Support\RecordingResetter;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * 1.29.1 (REQ-RUNTIME-002): the request memos are emptied at every boundary the bundle sees, without kernel.reset.
 */
final class WikiRequestBoundaryResetterTest extends TestCase
{
    public function testEveryBoundaryEmptiesTheMemos(): void
    {
        $memo       = self::memo();
        $other      = self::memo();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new WikiRequestBoundaryResetter([$memo, $other]));
        $kernel = $this->createStub(HttpKernelInterface::class);

        $dispatcher->dispatch(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST), KernelEvents::REQUEST);
        self::assertSame(1, $memo->resets);
        $dispatcher->dispatch(new RequestEvent($kernel, Request::create('/sub'), HttpKernelInterface::SUB_REQUEST), KernelEvents::REQUEST);
        self::assertSame(1, $memo->resets, 'a sub-request keeps the memo of its main request');

        foreach ([
            KernelEvents::TERMINATE,
            WikiRequestBoundaryResetter::CONSOLE_TERMINATE,
            WikiRequestBoundaryResetter::MESSENGER_HANDLED,
            WikiRequestBoundaryResetter::MESSENGER_FAILED,
        ] as $i => $event) {
            $dispatcher->dispatch(new stdClass(), $event);
            self::assertSame($i + 2, $memo->resets, $event);
            self::assertSame($i + 2, $other->resets, $event);
        }
    }

    public function testTheMessengerEventsAreTheFrameworkOnes(): void
    {
        self::assertSame('console.terminate', WikiRequestBoundaryResetter::CONSOLE_TERMINATE);
        self::assertStringEndsWith('\\WorkerMessageHandledEvent', WikiRequestBoundaryResetter::MESSENGER_HANDLED);
        self::assertStringEndsWith('\\WorkerMessageFailedEvent', WikiRequestBoundaryResetter::MESSENGER_FAILED);
        self::assertStringStartsWith('Symfony\\Component\\Messenger\\Event\\', WikiRequestBoundaryResetter::MESSENGER_FAILED);
    }

    private static function memo(): RecordingResetter
    {
        return new RecordingResetter();
    }
}
