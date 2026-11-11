<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Markdown\Milkdown\WikiFilePreviewFrames;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * 1.29.1 (REQ-RUNTIME-002, FrankenPHP worker mode with kernel reset false): empties the bundle's request memos at every
 * request boundary the bundle can see, so their correctness never depends on the host running `services_resetter`
 * (`kernel.reset`). Without it a worker kept the space memberships {@see LegacyWikiSpaceAccessChecker} read for a user
 * object the host EntityManager keeps managed, and a revoked membership kept granting access in that worker.
 *
 * Boundaries: the main `kernel.request` (before anything of the request runs) and `kernel.terminate`, the console's
 * `console.terminate` (long-running commands), and Messenger's `WorkerMessageHandledEvent` / `WorkerMessageFailedEvent`
 * (consumers). The Messenger events are subscribed by class name, so `symfony/messenger` stays optional.
 *
 * Cleared: {@see LegacyWikiSpaceAccessChecker}, {@see WikiAvatars}, {@see WikiFilePreviewFrames} (when file previews are
 * on), {@see WikiRenderContext} and {@see WikiEventDeferral}. Each one still implements
 * {@see ResetInterface} (`kernel.reset`) too.
 *
 * @internal
 */
final readonly class WikiRequestBoundaryResetter implements EventSubscriberInterface
{
    /** Messenger: a consumer finished a message (class name: messenger is optional). */
    public const MESSENGER_HANDLED = 'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent';

    /** Messenger: a consumer failed a message. */
    public const MESSENGER_FAILED = 'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent';

    /** `ConsoleEvents::TERMINATE` (symfony/console is required, the literal keeps the subscriber light). */
    public const CONSOLE_TERMINATE = 'console.terminate';

    /**
     * @param iterable<ResetInterface> $memos the request memos to empty
     */
    public function __construct(private iterable $memos)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Early, before the firewall (8) and the router (32): nothing of this request reads a memo before it.
            KernelEvents::REQUEST   => ['onKernelRequest', 2048],
            KernelEvents::TERMINATE => ['reset', -2048],
            self::CONSOLE_TERMINATE => ['reset', -2048],
            self::MESSENGER_HANDLED => ['reset', -2048],
            self::MESSENGER_FAILED  => ['reset', -2048],
        ];
    }

    /** A main request starts: nothing a previous request read may decide this one. */
    public function onKernelRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->reset();
        }
    }

    /** Empties every memo. */
    public function reset(): void
    {
        foreach ($this->memos as $memo) {
            $memo->reset();
        }
    }
}
