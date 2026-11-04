<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Panel;

use Nowo\WikiBundle\Panel\SessionWikiPanelStateStore;
use Nowo\WikiBundle\Panel\WikiPanelStateStoreInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * 1.16: the open / closed state of a side panel lives in the session, per user (not per page).
 */
final class SessionWikiPanelStateStoreTest extends TestCase
{
    public function testRemembersTheStatePerUserAndPanel(): void
    {
        $stack   = new RequestStack();
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack->push($request);
        $store = new SessionWikiPanelStateStore($stack);
        $ana   = new TestUser('a', 'ana@example.test');

        self::assertFalse($store->isOpen($ana, WikiPanelStateStoreInterface::COMMENTS));
        $store->setOpen($ana, WikiPanelStateStoreInterface::COMMENTS, true);
        self::assertTrue($store->isOpen($ana, WikiPanelStateStoreInterface::COMMENTS));
        self::assertFalse($store->isOpen($ana, 'other'));
        self::assertFalse($store->isOpen(new TestUser('b', 'bob@example.test'), WikiPanelStateStoreInterface::COMMENTS));
        $store->setOpen($ana, WikiPanelStateStoreInterface::COMMENTS, false);
        self::assertFalse($store->isOpen($ana, WikiPanelStateStoreInterface::COMMENTS));
    }

    public function testWithoutASessionNothingIsKept(): void
    {
        $stack = new RequestStack();
        $store = new SessionWikiPanelStateStore($stack);
        $user  = new TestUser();

        $store->setOpen($user, WikiPanelStateStoreInterface::COMMENTS, true);
        self::assertFalse($store->isOpen($user, WikiPanelStateStoreInterface::COMMENTS));

        $stack->push(Request::create('/'));
        $store->setOpen($user, WikiPanelStateStoreInterface::COMMENTS, true);
        self::assertFalse($store->isOpen($user, WikiPanelStateStoreInterface::COMMENTS));
    }
}
