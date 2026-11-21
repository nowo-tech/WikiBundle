<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Query;

use DateTimeImmutable;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use InvalidArgumentException;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Query\WikiArchiveFilter;
use Nowo\WikiBundle\Query\WikiOwnerUsage;
use Nowo\WikiBundle\Query\WikiPageCriteria;
use Nowo\WikiBundle\Query\WikiPageSort;
use Nowo\WikiBundle\Query\WikiQueryIds;
use Nowo\WikiBundle\Query\WikiQueryRows;
use Nowo\WikiBundle\Query\WikiSpaceCriteria;
use Nowo\WikiBundle\Query\WikiSpaceSort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_map;
use function count;
use function range;
use function str_repeat;

/**
 * 1.51: the input rules of the read ports (criteria, owner ids, paging, chunks) and the row conversions.
 */
final class WikiQueryInputTest extends TestCase
{
    public function testCriteriaKeepDistinctIdsAndDefaults(): void
    {
        $spaces = new WikiSpaceCriteria(WikiSpaceOwnerScope::Team, ['a', 'b', 'a'], ['s1', 's1', '']);
        self::assertSame(['a', 'b'], $spaces->ownerScopeIds);
        self::assertSame(['s1'], $spaces->spaceIds);
        self::assertSame(WikiArchiveFilter::Any, $spaces->archived);
        self::assertSame(WikiSpaceSort::NameAsc, $spaces->sort);
        self::assertFalse($spaces->matchesNothing());
        self::assertTrue((new WikiSpaceCriteria(null, null, []))->matchesNothing());
        self::assertNull((new WikiSpaceCriteria())->ownerScopeIds);

        $owned = WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::User, ['u-1'], WikiArchiveFilter::Active, WikiSpaceSort::SlugAsc);
        self::assertSame(WikiSpaceOwnerScope::User, $owned->ownerScopeType);
        self::assertSame(WikiArchiveFilter::Active, $owned->archived);
        self::assertSame(WikiSpaceSort::SlugAsc, $owned->sort);

        $pages = new WikiPageCriteria(null, WikiSpaceOwnerScope::Team, ['o'], ['p', 'p']);
        self::assertSame(['o'], $pages->ownerScopeIds);
        self::assertSame(['p'], $pages->pageIds);
        self::assertNull($pages->spaceIds);
        self::assertSame(WikiPageSort::Position, $pages->sort);
        self::assertTrue(WikiPageCriteria::inSpaces([])->matchesNothing());
        self::assertTrue((new WikiPageCriteria(pageIds: []))->matchesNothing());
        self::assertFalse(WikiPageCriteria::ofOwners(WikiSpaceOwnerScope::Team, ['o'], WikiArchiveFilter::Archived, WikiPageSort::TitleAsc)->matchesNothing());
    }

    public function testAPageCriteriaNeedsAScope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WikiPageCriteria();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableOwners(): iterable
    {
        yield 'empty' => [''];
        yield 'trailing space' => ['alice '];
        yield 'zero-width space' => ["ali\u{200B}ce"];
        yield 'too long' => [str_repeat('a', 65)];
    }

    #[DataProvider('unusableOwners')]
    public function testUnusableOwnerIdsAreRefused(string $owner): void
    {
        $this->expectException(InvalidArgumentException::class);
        WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, ['ok', $owner]);
    }

    public function testANonStringOwnerIdIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WikiQueryIds::owners([1]);
    }

    public function testAFilterTakesAtMostAThousandIds(): void
    {
        $ids = array_map(static fn (int $i): string => 'id-' . $i, range(1, WikiQueryIds::MAX_FILTER_IDS));
        self::assertCount(WikiQueryIds::MAX_FILTER_IDS, (new WikiPageCriteria($ids))->spaceIds ?? []);

        $this->expectException(InvalidArgumentException::class);
        new WikiPageCriteria([...$ids, 'one-more']);
    }

    public function testAnOwnerFilterTakesAtMostAThousandIds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WikiSpaceCriteria::forOwners(WikiSpaceOwnerScope::Team, array_map(static fn (int $i): string => 'o-' . $i, range(0, WikiQueryIds::MAX_FILTER_IDS)));
    }

    public function testIdsDropNonStringsAndChunk(): void
    {
        self::assertSame(['a', 'b'], WikiQueryIds::ids(['a', 1, null, 'b', 'a', '']));
        $chunks = WikiQueryIds::chunks(array_map(static fn (int $i): string => (string) $i, range(1, WikiQueryIds::CHUNK + 1)));
        self::assertSame(2, count($chunks));
        self::assertCount(WikiQueryIds::CHUNK, $chunks[0]);
        self::assertSame([(string) (WikiQueryIds::CHUNK + 1)], $chunks[1]);
        self::assertSame([], WikiQueryIds::chunks([]));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function badPaging(): iterable
    {
        yield 'negative offset' => [-1, 10];
        yield 'zero limit' => [0, 0];
        yield 'limit over the cap' => [0, WikiQueryIds::MAX_LIMIT + 1];
    }

    #[DataProvider('badPaging')]
    public function testPagingIsBounded(int $offset, int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        WikiQueryIds::paging($offset, $limit);
    }

    public function testRowsConvertScalarsAndDates(): void
    {
        $platform = new SQLitePlatform();
        $date     = new DateTimeImmutable('2026-11-21 10:00:00');
        self::assertNull(WikiQueryRows::aggregateDate(null, $platform));
        self::assertSame($date, WikiQueryRows::aggregateDate($date, $platform));
        self::assertEquals($date, WikiQueryRows::aggregateDate('2026-11-21 10:00:00', $platform));
        self::assertSame('', WikiQueryRows::string(new stdClass()));
        self::assertSame('7', WikiQueryRows::string(7));
        self::assertNull(WikiQueryRows::stringOrNull(null));
        self::assertSame(0, WikiQueryRows::int('x'));
        self::assertSame(12, WikiQueryRows::int('12'));

        $space = WikiQueryRows::space([
            'id' => 's', 'slug' => 'slug', 'name' => 'Name', 'ownerScopeType' => 'team', 'ownerScopeId' => 'o',
            'createdAt' => '2026-11-21 10:00:00', 'updatedAt' => $date, 'icon' => null, 'archivedAt' => '2026-11-21 11:00:00', 'version' => '3',
        ]);
        self::assertSame(WikiSpaceOwnerScope::Team, $space->ownerScopeType);
        self::assertEquals($date, $space->createdAt);
        self::assertTrue($space->isArchived());
        self::assertSame(3, $space->version);

        $page = WikiQueryRows::page([
            'id' => 'p', 'spaceId' => 's', 'slug' => 'slug', 'title' => 'Title', 'parentId' => null, 'position' => 2,
            'currentRevisionId' => 'r', 'archivedAt' => null, 'createdAt' => $date, 'updatedAt' => $date, 'icon' => 'tabler:book',
        ]);
        self::assertNull($page->parentId);
        self::assertSame(2, $page->position);
        self::assertSame('tabler:book', $page->icon);
        self::assertFalse($page->isArchived());
    }

    public function testUsageAddsUp(): void
    {
        $total = WikiOwnerUsage::total([new WikiOwnerUsage('a', 1, 2, 3, 4, 5), new WikiOwnerUsage('b', 10, 20, 30, 40, 50)], 'payer');

        self::assertEquals(new WikiOwnerUsage('payer', 11, 22, 33, 44, 55), $total);
        self::assertEquals(new WikiOwnerUsage(''), WikiOwnerUsage::total([]));
    }
}
