<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Api;

use Nowo\WikiBundle\Api\WikiApiQuery;
use Nowo\WikiBundle\Api\WikiApiRefusal;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

use function str_repeat;

final class WikiApiQueryTest extends TestCase
{
    public function testDates(): void
    {
        self::assertNull($this->query([])->date('since'));
        self::assertSame(1_700_000_000, $this->query(['since' => '1700000000123'])->date('since')?->getTimestamp());
        $this->refused(fn () => $this->query(['since' => 'yesterday'])->date('since'), 'since');
    }

    public function testRepeatableStrings(): void
    {
        self::assertSame([], $this->query([])->strings('wiki'));
        self::assertSame(['eng'], $this->query(['wiki' => 'eng'])->strings('wiki'));
        self::assertSame(['eng', 'ops'], $this->query(['wiki' => ['eng', 'ops']])->strings('wiki'));
        $this->refused(fn () => $this->query(['wiki' => ['a' => 'eng']])->strings('wiki'), 'wiki');
        $this->refused(fn () => $this->query(['wiki' => ['eng', ['x']]])->strings('wiki'), 'wiki');
        $this->refused(fn () => $this->query(['wiki' => ['']])->strings('wiki'), 'wiki');
        $this->refused(fn () => $this->query(['wiki' => ['a', 'b', 'c']])->strings('wiki', 2), 'wiki');
    }

    public function testStringsAndBooleans(): void
    {
        self::assertSame('x', $this->query(['q' => 'x'])->string('q'));
        self::assertFalse($this->query(['a' => '0'])->bool('a'));
        self::assertTrue($this->query(['a' => '1'])->bool('a'));
        self::assertSame(0, $this->query([])->page());
        self::assertSame(3, $this->query(['page' => '3'])->page());
        $this->refused(fn () => $this->query(['q' => str_repeat('x', 300)])->string('q'), 'q');
        $this->refused(fn () => $this->query(['q' => "\xff"])->string('q'), 'q');
        $this->refused(fn () => $this->query(['page' => '10000'])->page(), 'page');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function query(array $query): WikiApiQuery
    {
        return new WikiApiQuery(new Request($query));
    }

    private function refused(callable $call, string $field): void
    {
        try {
            $call();
            self::fail('Expected a refusal of ' . $field);
        } catch (WikiApiRefusal $refusal) {
            self::assertSame([400, 'INPUT_002', 'Invalid field: ' . $field], [$refusal->status, $refusal->ecode, $refusal->getMessage()]);
        }
    }
}
