<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Counts its resets (request boundary tests).
 */
final class RecordingResetter implements ResetInterface
{
    public int $resets = 0;

    public function reset(): void
    {
        ++$this->resets;
    }
}
