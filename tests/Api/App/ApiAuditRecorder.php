<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\WikiBundle\Event\WikiApiWriteEvent;

/**
 * The host's audit of API writes in the tests (static: the kernel boots per request).
 */
final class ApiAuditRecorder
{
    /** @var list<WikiApiWriteEvent> */
    public static array $events = [];

    public function record(WikiApiWriteEvent $event): void
    {
        self::$events[] = $event;
    }
}
