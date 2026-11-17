<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use DateTimeImmutable;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Trash\WikiTrashService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function assert;
use function json_encode;
use function sprintf;

/**
 * 1.40.2 (REQ-RUNTIME-002): one kept-alive kernel, requests straight through `http_kernel` (no services resetter, the
 * application's manager never cleared), mixing trash delete, trash purge, restore, tree move and page save. Up to
 * 1.40.1 a purge left the trash entries, the current revision and the surviving sub-pages of the purged pages managed
 * and pointing at detached pages: the next flush on that manager (any later wiki write of the worker) failed with
 * `ORMInvalidArgumentException` ("new entity found through WikiTrashEntry#page").
 */
final class WorkerNoResetTrashTest extends ApiTestCase
{
    private const ROUNDS = 6;

    /** @var array<string, mixed> */
    protected static array $wikiConfig = ['trash' => ['enabled' => true]];

    public function testTrashPurgeMoveAndSaveInTurnOnOneKernel(): void
    {
        $trash  = $this->trash();
        $keeper = $this->page($this->eng, 'Keeper', 'v1');
        $target = $this->page($this->eng, 'Target', 'x');
        $saves  = 1;

        for ($round = 0; $round < self::ROUNDS; ++$round) {
            $parent = $this->page($this->eng, sprintf('Parent %d', $round), 'p');
            $child  = $this->page($this->eng, sprintf('Child %d', $round), 'c', $parent);
            $loose  = $this->page($this->eng, sprintf('Loose %d', $round), 'l', $parent);

            // The tree move of the e2e (Alt+arrow): a request on the same kernel.
            $moved = $this->raw('POST', '/wikis/eng/pages/' . $loose->getId() . '/move', ['parent' => null, 'index' => 0]);
            self::assertSame(200, $moved->getStatusCode(), $round . ': ' . $moved->getContent());

            // The sub-page goes to the trash first, then its parent (a separate deletion), then the parent is purged:
            // the sub-page stays in the trash with its parent pointer cleared.
            $childBatch  = $trash->delete($this->fresh($child));
            $parentBatch = $trash->delete($this->fresh($parent));
            self::assertSame(1, $trash->purge($this->eng(), $parentBatch), (string) $round);

            // Every wiki write after the purge, on the same kernel and manager.
            $saved = $this->raw('PUT', '/wikis/eng/pages/' . $keeper->getId(), ['content_markdown' => 'v' . ($saves + 1), 'base_revision' => $saves]);
            self::assertSame(200, $saved->getStatusCode(), $round . ': ' . $saved->getContent());
            ++$saves;
            $moved = $this->raw('POST', '/wikis/eng/pages/' . $loose->getId() . '/move', ['parent' => $target->getId(), 'index' => 0]);
            self::assertSame(200, $moved->getStatusCode(), $round . ': ' . $moved->getContent());

            $restored = $trash->restore($this->eng(), $childBatch);
            self::assertInstanceOf(WikiPage::class, $restored);
            self::assertNull($restored->getParent(), 'the restored sub-page lost its purged parent: top level');
            self::assertFalse($restored->isArchived());

            $looseBatch = $trash->delete($this->fresh($loose));
            self::assertSame(1, $trash->purge($this->eng(), $looseBatch));
        }

        $wiki = $this->wikiEm();
        $wiki->clear();
        $count = static fn (string $class): int => (int) $wiki->createQueryBuilder()->select('COUNT(x.id)')->from($class, 'x')->getQuery()->getSingleScalarResult();
        // Keeper, Target and one restored sub-page per round.
        self::assertSame(2 + self::ROUNDS, $count(WikiPage::class));
        self::assertSame(0, $count(WikiTrashEntry::class));
        $keeper = $wiki->find(WikiPage::class, $keeper->getId());
        self::assertInstanceOf(WikiPage::class, $keeper);
        self::assertSame($saves, $keeper->getCurrentRevision()?->getRevisionNumber());
    }

    /** `wiki:trash:purge` in one unit of work: several expired deletions purged in turn, then a write. */
    public function testSeveralPurgesInOneUnitOfWorkThenAWrite(): void
    {
        $trash = $this->trash();
        for ($i = 0; $i < 3; ++$i) {
            $parent = $this->page($this->eng, sprintf('Old %d', $i), 'o');
            $this->page($this->eng, sprintf('Old child %d', $i), 'c', $parent);
            $trash->delete($this->fresh($parent));
        }
        $keeper = $this->page($this->eng, 'Keeper', 'v1');

        self::assertSame(['batches' => 3, 'pages' => 6], $trash->purgeExpired(new DateTimeImmutable('+1 year')));
        $saved = $this->raw('PUT', '/wikis/eng/pages/' . $keeper->getId(), ['content_markdown' => 'v2', 'base_revision' => 1]);
        self::assertSame(200, $saved->getStatusCode(), (string) $saved->getContent());
    }

    private function trash(): WikiTrashService
    {
        $trash = static::getContainer()->get(WikiTrashService::class);
        assert($trash instanceof WikiTrashService);

        return $trash;
    }

    /** The page as the wiki's manager holds it now (what a request would load). */
    private function fresh(WikiPage $page): WikiPage
    {
        $found = $this->wikiEm()->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $found);

        return $found;
    }

    private function eng(): WikiSpace
    {
        $space = $this->wikiEm()->find(WikiSpace::class, $this->eng->getId());
        self::assertNotNull($space);

        return $space;
    }

    /**
     * One request straight through the container's `http_kernel`: no kernel boot check, no services resetter, no clear.
     *
     * @param array<string, mixed> $body
     */
    private function raw(string $method, string $path, array $body): Response
    {
        $kernel = static::getContainer()->get('http_kernel');
        assert($kernel instanceof HttpKernelInterface && $kernel instanceof TerminableInterface);
        $server   = ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . self::ANA, 'CONTENT_TYPE' => 'application/json'];
        $request  = Request::create('/api/wiki/v1' . $path, $method, [], [], [], $server, (string) json_encode($body));
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
    }
}
