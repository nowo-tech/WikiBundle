<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Collaboration;

use DateTimeImmutable;

/**
 * A co-editing store that reports which page logs it purged (1.13.1, CW-I10), so the live editors of those pages can be
 * told to reload. Implemented by the Doctrine store; optional for custom stores.
 */
interface WikiCollabPagePurgeInterface
{
    /**
     * Deletes, under a lock, the logs of pages untouched since `$before` and returns their page ids.
     *
     * @return list<string>
     */
    public function purgePages(DateTimeImmutable $before): array;
}
