<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiAuditRecorder;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;

use function array_map;

/**
 * 1.35: `POST /wikis/{wiki}/pages` with `position` — the 1-based place among the siblings the token's user may read,
 * created under the space lock with the ordering of the moves; omitted, the create is as before.
 */
final class ApiPageCreatePositionTest extends ApiTestCase
{
    public function testAboveBelowFirstLastAndChild(): void
    {
        $a = $this->page($this->eng, 'A', 'x');
        $this->page($this->eng, 'B', 'x');
        $this->page($this->eng, 'C', 'x');

        // Between A and B ("below A" / "above B"): the siblings created before 1.14 are renumbered once.
        $x = $this->create(['title' => 'X', 'position' => 2]);
        self::assertSame(201, $x['status']);
        self::assertNull($x['body']['parent']);
        self::assertSame(['A', 'X', 'B', 'C'], $this->order(null));
        self::assertSame(['title', 'content_markdown', 'position'], ApiAuditRecorder::$events[0]->changedFields);

        $this->create(['title' => 'First', 'position' => 1]);
        $this->create(['title' => 'Last', 'position' => 99]);
        self::assertSame(['First', 'A', 'X', 'B', 'C', 'Last'], $this->order(null));

        // As child: the last place among the parent's children.
        $this->create(['title' => 'Kid 1', 'parent' => $a->getId(), 'position' => 1]);
        $kid = $this->create(['title' => 'Kid 2', 'parent' => $a->getId(), 'position' => 2]);
        self::assertSame($a->getId(), $kid['body']['parent']);
        self::assertSame(['Kid 1', 'Kid 2'], $this->order($a->getId()));

        // Without position: as before (position 0, first by the sort, no "position" in the audit).
        ApiAuditRecorder::$events = [];
        $plain                    = $this->create(['title' => 'Plain']);
        self::assertSame(201, $plain['status']);
        self::assertSame(['title', 'content_markdown'], ApiAuditRecorder::$events[0]->changedFields);
        self::assertSame(0, $this->em()->find(WikiPage::class, $plain['body']['id'])?->getPosition());
    }

    public function testHiddenSiblingsAreNotCountedAndKeepTheirPlace(): void
    {
        $this->page($this->eng, 'A', 'x');
        $this->page($this->eng, 'B', 'x');
        $this->page($this->eng, 'C', 'x');
        ApiTestHost::$hiddenPages = ['b'];

        // Position 2 among what the token's user sees (A, C) = right before C, after the hidden B.
        $this->create(['title' => 'X', 'position' => 2]);

        ApiTestHost::$hiddenPages = [];
        self::assertSame(['A', 'B', 'X', 'C'], $this->order(null));
    }

    public function testInvalidPositions(): void
    {
        foreach ([0, -1, '2', 1.5, 100001, true] as $position) {
            $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'X', 'position' => $position]), 400, 'INPUT_002');
        }
        self::assertSame([], ApiAuditRecorder::$events);
    }

    public function testTheParentNeedsTheEditRightAndMustBeVisible(): void
    {
        $guide = $this->page($this->eng, 'Guide', 'x');

        ApiTestHost::$grants['u-ana']['eng'] = [WikiAction::View, WikiAction::Create];
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Child', 'parent' => $guide->getId(), 'position' => 1]), 403, 'ACCESS_002');

        ApiTestHost::$grants['u-ana']['eng'] = ApiTestHost::all();
        ApiTestHost::$hiddenPages            = ['guide'];
        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Child', 'parent' => $guide->getId(), 'position' => 1]), 404, 'ACCESS_001');

        // No create right on the wiki (a private wiki of someone else): refused before the place is looked at.
        $this->assertRefusal($this->call('POST', '/wikis/ops/pages', self::ANA, ['title' => 'Child', 'position' => 1]), 403, 'ACCESS_002');
        self::assertSame([], ApiAuditRecorder::$events);
    }

    public function testAParentArchivedMeanwhileIsGoneAndNothingIsWritten(): void
    {
        $a = $this->page($this->eng, 'A', 'x');
        // Archived once its Edit right was checked, before the tree's lock.
        ApiTestHost::$race = static function (WikiPage $target) use ($a): bool {
            if ($target->getId() !== $a->getId()) {
                return false;
            }
            self::getContainer()->get('doctrine.dbal.default_connection')->executeStatement('UPDATE wiki_pages SET archived_at = ? WHERE id = ?', ['2026-11-14 10:00:00', $a->getId()]);

            return true;
        };

        $this->assertRefusal($this->call('POST', '/wikis/eng/pages', self::ANA, ['title' => 'Late', 'parent' => $a->getId(), 'position' => 1]), 404, 'ACCESS_001');
        self::assertNull($this->em()->getRepository(WikiPage::class)->findOneBy(['slug' => 'late']));
        self::assertSame([], ApiAuditRecorder::$events);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    private function create(array $body): array
    {
        $response = $this->call('POST', '/wikis/eng/pages', self::ANA, $body);

        return ['status' => $response->getStatusCode(), 'body' => $this->json($response)];
    }

    /**
     * @return list<string> titles of the active children of the parent (null: the top level), in tree order
     */
    private function order(?string $parentId): array
    {
        $em = $this->em();
        $em->clear();
        $pages = $em->getRepository(WikiPage::class)->findBy(['space' => $this->eng->getId(), 'parent' => $parentId, 'archivedAt' => null], ['position' => 'ASC', 'title' => 'ASC']);

        return array_map(static fn (WikiPage $page): string => $page->getTitle(), $pages);
    }
}
