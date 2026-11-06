<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Nowo\WikiBundle\Event\WikiQuotaCheckEvent;

/**
 * `nowo_wiki.quota.check` listener of the kernel tests: records every check and refuses while a reason is set. State is
 * static because the test sets it before the kernel builds the service.
 */
final class RecordingQuotaListener
{
    /** @var list<WikiQuotaCheckEvent> */
    public static array $seen = [];

    public static ?string $denyWith = null;

    public function onCheck(WikiQuotaCheckEvent $event): void
    {
        self::$seen[] = $event;
        if (self::$denyWith !== null) {
            $event->deny(self::$denyWith);
        }
    }
}
