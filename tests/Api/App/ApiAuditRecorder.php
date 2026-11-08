<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Doctrine\DBAL\Connection;
use Nowo\WikiBundle\Event\WikiApiWriteEvent;
use Nowo\WikiBundle\Service\WikiPageSavedEvent;
use RuntimeException;

/**
 * The host's audit of API writes in the tests, and the page events, each with the transaction nesting level it was heard
 * at (0: after the outermost commit; audit IF-L2). Static: the tests read it after the request.
 */
final class ApiAuditRecorder
{
    /** @var list<WikiApiWriteEvent> */
    public static array $events = [];

    /** @var list<array{0: string, 1: int}> event name => nesting level */
    public static array $heard = [];

    /** A page listener that fails after the write (a mailer down; audit IF-L3). */
    public static bool $failSaved = false;

    public function __construct(private readonly ?Connection $connection = null)
    {
    }

    public function record(WikiApiWriteEvent $event): void
    {
        self::$events[] = $event;
        self::$heard[]  = ['api_write', $this->level()];
    }

    public function saved(WikiPageSavedEvent $event): void
    {
        self::$heard[] = ['page_saved', $this->level()];
        if (self::$failSaved) {
            throw new RuntimeException('The mailer is down.');
        }
    }

    private function level(): int
    {
        return $this->connection?->getTransactionNestingLevel() ?? -1;
    }
}
