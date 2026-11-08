<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Service;

use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function max;

/**
 * 1.24 (spec 218, audit IF-L2): holds the page events of a write that runs inside an outer transaction (the co-editing
 * log's lock) until that transaction has committed, so no listener — mentions, backlinks, file references, realtime,
 * audit — acts on a write that is then rolled back. Outside such a scope an event is dispatched at once, as before.
 *
 * Request state, emptied by `kernel.reset` (worker mode).
 */
final class WikiEventDeferral implements ResetInterface
{
    private int $depth = 0;

    /** @var list<array{0: EventDispatcherInterface, 1: object, 2: string}> */
    private array $queue = [];

    /**
     * Runs a unit of work that commits on return: the events it held are dispatched after it returned, and dropped when
     * it threw. Nested scopes dispatch at the end of the outermost one.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed
    {
        ++$this->depth;
        try {
            $result = $work();
        } catch (Throwable $exception) {
            if ($this->leave() === 0) {
                $this->queue = [];
            }

            throw $exception;
        }
        if ($this->leave() === 0) {
            $queue       = $this->queue;
            $this->queue = [];
            foreach ($queue as [$dispatcher, $event, $name]) {
                $dispatcher->dispatch($event, $name);
            }
        }

        return $result;
    }

    /** Dispatches now, or at the end of the open scope. */
    public function dispatch(EventDispatcherInterface $dispatcher, object $event, string $name): void
    {
        if ($this->depth === 0) {
            $dispatcher->dispatch($event, $name);

            return;
        }
        $this->queue[] = [$dispatcher, $event, $name];
    }

    /** Leaves a scope; never below zero, even after a reset inside one. */
    private function leave(): int
    {
        $this->depth = max(0, $this->depth - 1);

        return $this->depth;
    }

    public function reset(): void
    {
        $this->depth = 0;
        $this->queue = [];
    }
}
