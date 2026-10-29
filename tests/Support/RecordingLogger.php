<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * PSR-3 logger that keeps every record for assertions.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<string> "level: message" per record
     */
    public function lines(): array
    {
        return array_map(static fn (array $r): string => $r['level'] . ': ' . $r['message'], $this->records);
    }
}
