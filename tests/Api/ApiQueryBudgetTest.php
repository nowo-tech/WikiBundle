<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Api\WikiApiLevel;
use Nowo\WikiBundle\Tests\Support\RecordingLogger;

use function array_filter;
use function count;
use function str_contains;

/**
 * 1.24 (spec 218 FR-019, SC-005): lists and search take the same number of queries with 25 pages as with 100.
 */
final class ApiQueryBudgetTest extends ApiTestCase
{
    public function testListsAndSearchStayWithinABudget(): void
    {
        $this->token('bob', 'u-bob', WikiApiLevel::Read);
        for ($i = 0; $i < 25; ++$i) {
            $this->page($this->eng, 'Page ' . $i, 'common text', null, $i % 2 === 0 ? $this->ana : $this->bob);
        }
        $small = [$this->queries('/wikis/eng/pages'), $this->queries('/search?q=common')];

        for ($i = 25; $i < 100; ++$i) {
            $this->page($this->eng, 'Page ' . $i, 'common text', null, $i % 2 === 0 ? $this->ana : $this->bob);
        }
        $large = [$this->queries('/wikis/eng/pages'), $this->queries('/search?q=common')];

        self::assertSame($small, $large);
        self::assertLessThanOrEqual(8, $large[0]);
    }

    private function queries(string $path): int
    {
        $logger = static::getContainer()->get('logger');
        self::assertInstanceOf(RecordingLogger::class, $logger);
        $this->em()->clear();
        $logger->records = [];
        self::assertSame(200, $this->call('GET', $path, 'bob')->getStatusCode());

        return count(array_filter($logger->records, static fn (array $record): bool => str_contains($record['message'], 'Executing')));
    }
}
