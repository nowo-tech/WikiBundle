<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiTrashEntry;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Tests\Api\App\ApiTestHost;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Symfony\Component\HttpFoundation\Response;

use function array_column;
use function json_encode;
use function preg_match;

use const JSON_THROW_ON_ERROR;

/**
 * 1.41 (tree.context_menu): the tree menu's entries over HTTP on one kept-alive kernel (no reboot between requests, as a
 * FrankenPHP worker without kernel reset), in the shared and the private entity manager modes (`WIKI_TEST_PRIVATE_EM`):
 * the marks follow the rights, every write asks the session's CSRF token and the same origin, Delete sends a page and
 * its sub-pages to the trash, Duplicate copies right below, Move refuses a cycle, Archive / Unarchive round-trip.
 */
final class TreeContextMenuWebTest extends ApiTestCase
{
    /** @var array<string, mixed> */
    protected static array $wikiConfig = [
        'tree'  => ['enabled' => true, 'context_menu' => true],
        'trash' => ['enabled' => true],
    ];

    protected static bool $slugLocator = true;

    public function testTheMenuFollowsTheRights(): void
    {
        $page = $this->page($this->eng, 'Home', 'x');
        $html = $this->signedInPage($this->ana);
        self::assertStringContainsString('data-wiki-tree-menu ', $html);
        foreach (['data-wiki-menu-edit', 'data-wiki-menu-move', 'data-wiki-menu-archive', 'data-wiki-menu-delete', 'data-wiki-new-sibling'] as $mark) {
            self::assertStringContainsString($mark . '="1"', $html);
        }

        // A reader: no menu and no mark; every entry refused by the server too.
        $html = $this->signedInPage($this->bob);
        self::assertStringNotContainsString('data-wiki-tree-menu ', $html);
        self::assertStringNotContainsString('data-wiki-menu-', $html);
        $token = $this->treeToken($html);
        foreach (['archive', 'delete', 'duplicate', 'parents', 'rename'] as $action) {
            self::assertSame(403, $this->post($action, ['page' => $page->getId(), 'title' => 'X'], $token)->getStatusCode(), $action);
        }

        // Archive without Delete: no Delete entry.
        ApiTestHost::$grants['u-bob']['eng'] = [WikiAction::View, WikiAction::Archive];
        $html                                = $this->signedInPage($this->bob);
        self::assertStringContainsString('data-wiki-menu-archive="1"', $html);
        self::assertStringNotContainsString('data-wiki-menu-delete', $html);
        self::assertStringNotContainsString('data-wiki-menu-edit', $html);
        self::assertSame(403, $this->post('delete', ['page' => $page->getId()], $this->treeToken($html))->getStatusCode());
        self::assertSame(200, $this->post('archive', ['page' => $page->getId()], $this->treeToken($html))->getStatusCode());
    }

    public function testEveryWriteAsksTheTokenAndTheOrigin(): void
    {
        $page  = $this->page($this->eng, 'Home', 'x');
        $token = $this->treeToken($this->signedInPage($this->ana));
        self::assertSame(403, $this->post('archive', ['page' => $page->getId()], 'forged')->getStatusCode());
        self::assertSame(403, $this->post('archive', ['page' => $page->getId()], $token, 'https://evil.example')->getStatusCode());
        $this->client->request('GET', '/tools/wiki/spaces/eng/tree/archive');
        self::assertSame(405, $this->client->getResponse()->getStatusCode());
        self::assertFalse($this->fresh($page)->isArchived());
    }

    public function testArchiveDeleteDuplicateAndMoveOnOneKernel(): void
    {
        $parent                   = $this->page($this->eng, 'Parent', 'p');
        $child                    = $this->page($this->eng, 'Child', 'c', $parent);
        $hidden                   = $this->page($this->eng, 'Hidden', 'h', $parent);
        $other                    = $this->page($this->eng, 'Other', 'o');
        ApiTestHost::$hiddenPages = [$hidden->getSlug()];
        $token                    = $this->treeToken($this->signedInPage($this->ana));

        // Archive, then the undo.
        self::assertSame(200, $this->post('archive', ['page' => $other->getId()], $token)->getStatusCode());
        self::assertTrue($this->fresh($other)->isArchived());
        self::assertSame(200, $this->post('unarchive', ['page' => $other->getId()], $token)->getStatusCode());
        self::assertFalse($this->fresh($other)->isArchived());

        // Move: a cycle is refused, a move to the top level is placed.
        $cycle = $this->post('move', ['page' => $parent->getId(), 'parent' => $child->getId(), 'index' => 0], $token);
        self::assertSame(422, $cycle->getStatusCode());
        self::assertSame('cycle', $this->json($cycle)['error']);
        $parents = $this->json($this->post('parents', ['page' => $parent->getId()], $token));
        self::assertSame([$other->getId()], array_column($parents['parents'], 'id'));
        self::assertTrue($parents['root']);

        // Duplicate: right below, the page only, then its page shows the flash.
        $duplicate = $this->json($this->post('duplicate', ['page' => $child->getId()], $token));
        $copy      = $this->wikiEm()->find(WikiPage::class, $duplicate['page']);
        self::assertInstanceOf(WikiPage::class, $copy);
        self::assertSame('Child (copy)', $copy->getTitle());
        self::assertSame($parent->getId(), $copy->getParent()?->getId());
        $this->client->request('GET', (string) $duplicate['url']);
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('“Child (copy)” created from “Child”.', (string) $this->client->getResponse()->getContent());

        // Reorder: the copy moves up above Child (the tree's Alt+arrow move).
        $up = $this->post('move', ['page' => $copy->getId(), 'parent' => $parent->getId(), 'index' => 0], $token);
        self::assertSame(200, $up->getStatusCode(), (string) $up->getContent());
        self::assertSame(0, $this->json($up)['index']);

        // Delete: Parent and its sub-pages go to the trash; the count holds what the user may read.
        $preview = $this->json($this->post('delete', ['page' => $parent->getId(), 'preview' => true], $token));
        self::assertSame(3, $preview['count']);
        self::assertSame(200, $this->post('delete', ['page' => $parent->getId()], $token)->getStatusCode());
        $this->wikiEm()->clear();
        self::assertCount(4, $this->wikiEm()->getRepository(WikiTrashEntry::class)->findAll());
        self::assertTrue($this->fresh($child)->isArchived());
        self::assertTrue($this->fresh($hidden)->isArchived());

        // Every later write on the same kernel still works (REQ-RUNTIME-002).
        self::assertSame(200, $this->post('rename', ['page' => $other->getId(), 'title' => 'Other renamed'], $token)->getStatusCode());
        self::assertSame('Other renamed', $this->fresh($other)->getTitle());
    }

    private function signedInPage(TestUserEntity $user): string
    {
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($user, 'main');
        $this->client->request('GET', '/tools/wiki/spaces/eng');
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return (string) $response->getContent();
    }

    private function treeToken(string $html): string
    {
        self::assertSame(1, preg_match('/data-wiki-tree-token="([^"]+)"/', $html, $match));

        return $match[1];
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $action, array $body, string $token, string $origin = 'http://localhost'): Response
    {
        $this->client->request('POST', '/tools/wiki/spaces/eng/tree/' . $action, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT'  => 'application/json',
            'HTTP_ORIGIN'  => $origin,
        ], json_encode($body + ['_token' => $token], JSON_THROW_ON_ERROR));

        return $this->client->getResponse();
    }

    private function fresh(WikiPage $page): WikiPage
    {
        $this->wikiEm()->clear();
        $found = $this->wikiEm()->find(WikiPage::class, $page->getId());
        self::assertInstanceOf(WikiPage::class, $found);

        return $found;
    }
}
