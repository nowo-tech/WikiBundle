<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Tree;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tree\SessionWikiTreeStateStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

use function range;

/**
 * 1.14: the fold state of the page tree lives in the session, per user and space, capped.
 */
final class SessionWikiTreeStateStoreTest extends TestCase
{
    public function testKeepsTheExpandedPagesPerUserAndSpace(): void
    {
        [$store, $session] = $this->store();
        $eng               = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $ops               = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');
        $alice             = new TestUser('a', 'alice@example.test');

        $store->setExpanded($alice, $eng, 'p1', true);
        $store->setExpanded($alice, $eng, 'p2', true);
        $store->setExpanded($alice, $eng, 'p1', true);
        $store->setExpanded($alice, $ops, 'p9', true);
        self::assertSame(['p2', 'p1'], $store->expanded($alice, $eng));
        self::assertSame(['p9'], $store->expanded($alice, $ops));
        self::assertSame([], $store->expanded(new TestUser('b', 'bob@example.test'), $eng));

        $store->setExpanded($alice, $eng, 'p2', false);
        self::assertSame(['p1'], $store->expanded($alice, $eng));

        // Foreign values in the session are ignored.
        foreach ($session->all() as $key => $value) {
            $session->set($key, ['ok', 3, null]);
        }
        self::assertSame(['ok'], $store->expanded($alice, $eng));
        $session->set('x', 'y');
        foreach ($session->all() as $key => $value) {
            if ($key !== 'x') {
                $session->set($key, 'not a list');
            }
        }
        self::assertSame([], $store->expanded($alice, $eng));
    }

    public function testCapsTheListDroppingTheOldestIds(): void
    {
        [$store] = $this->store();
        $space   = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $user    = new TestUser('alice');
        foreach (range(1, SessionWikiTreeStateStore::MAX_IDS + 2) as $i) {
            $store->setExpanded($user, $space, 'p' . $i, true);
        }

        $ids = $store->expanded($user, $space);
        self::assertCount(SessionWikiTreeStateStore::MAX_IDS, $ids);
        self::assertSame('p3', $ids[0]);
    }

    public function testWithoutSessionNothingIsKept(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $user  = new TestUser('alice');
        $stack = new RequestStack();
        $store = new SessionWikiTreeStateStore($stack);
        $store->setExpanded($user, $space, 'p1', true);
        self::assertSame([], $store->expanded($user, $space));

        $stack->push(Request::create('/'));
        $store->setExpanded($user, $space, 'p1', true);
        self::assertSame([], $store->expanded($user, $space));
    }

    /**
     * @return array{0: SessionWikiTreeStateStore, 1: Session}
     */
    private function store(): array
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = new RequestStack();
        $stack->push($request);

        return [new SessionWikiTreeStateStore($stack), $session];
    }
}
