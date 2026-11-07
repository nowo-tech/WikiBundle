<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Search;

use Nowo\WikiBundle\Search\WikiSearchScopeListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * 1.21 `search.scope: space`: the search page answers inside one space only.
 */
final class WikiSearchScopeListenerTest extends TestCase
{
    public function testOtherRoutesAreLeftAlone(): void
    {
        $request = $this->request('/tools/wiki/spaces/eng', 'nowo_wiki_space');
        (new WikiSearchScopeListener('nowo_wiki_search'))($this->event($request));

        self::assertFalse($request->attributes->has('spaceSlug'));
    }

    public function testTheSpaceBecomesTheRequestSpace(): void
    {
        $request = $this->request('/tools/wiki/search?space=0f8fad5b-d9cb-469f-a165-70867728950e&q=x', 'nowo_wiki_search');
        $request->attributes->set('_route_params', ['_locale' => 'en']);
        (new WikiSearchScopeListener('nowo_wiki_search'))($this->event($request));

        self::assertSame('0f8fad5b-d9cb-469f-a165-70867728950e', $request->attributes->get('spaceSlug'));
        self::assertSame(['spaceSlug' => '0f8fad5b-d9cb-469f-a165-70867728950e', '_locale' => 'en'], $request->attributes->get('_route_params'));

        $bare = $this->request('/tools/wiki/search?space=eng', 'nowo_wiki_search');
        (new WikiSearchScopeListener('nowo_wiki_search'))($this->event($bare));
        self::assertSame(['spaceSlug' => 'eng'], $bare->attributes->get('_route_params'));
    }

    /**
     * A search without a space, or with one that is not a slug, is 404.
     */
    public function testASearchWithoutAValidSpaceIsNotFound(): void
    {
        foreach (['/tools/wiki/search?q=x', '/tools/wiki/search?space=', '/tools/wiki/search?space=Eng/../x', '/tools/wiki/search?space[]=eng'] as $uri) {
            try {
                (new WikiSearchScopeListener('nowo_wiki_search'))($this->event($this->request($uri, 'nowo_wiki_search')));
                self::fail('Accepted ' . $uri);
            } catch (NotFoundHttpException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** What the tenant_paths routes accept is accepted here too, and nothing beyond it. */
    public function testACompositeSlugPassesOnlyWithItsTenantRequirement(): void
    {
        $listener = new WikiSearchScopeListener('nowo_wiki_search', ['org-[0-9a-f]+(?:--[a-z0-9-]+)?']);
        $request  = $this->request('/tools/wiki/search?space=org-abc1--handbook', 'nowo_wiki_search');
        $listener($this->event($request));
        self::assertSame('org-abc1--handbook', $request->attributes->get('spaceSlug'));

        $bare = $this->request('/tools/wiki/search?space=eng', 'nowo_wiki_search');
        $listener($this->event($bare));
        self::assertSame('eng', $bare->attributes->get('spaceSlug'));

        foreach (['space=org-abc1--', 'space=org-abc1--handbook%0A', 'space=org-abc1--a/../b', 'space=x--org-abc1', 'space=org-abc1--A'] as $query) {
            try {
                $listener($this->event($this->request('/tools/wiki/search?' . $query, 'nowo_wiki_search')));
                self::fail('Accepted ' . $query);
            } catch (NotFoundHttpException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(NotFoundHttpException::class);
        (new WikiSearchScopeListener('nowo_wiki_search'))($this->event($this->request('/tools/wiki/search?space=org-abc1--handbook', 'nowo_wiki_search')));
    }

    public function testAnExcludedSpaceHasNoSearchPage(): void
    {
        $listener = new WikiSearchScopeListener('nowo_wiki_search', [], ['general']);
        $other    = $this->request('/tools/wiki/search?space=eng', 'nowo_wiki_search');
        $listener($this->event($other));
        self::assertSame('eng', $other->attributes->get('spaceSlug'));

        $this->expectException(NotFoundHttpException::class);
        $listener($this->event($this->request('/tools/wiki/search?space=general', 'nowo_wiki_search')));
    }

    private function request(string $uri, string $route): Request
    {
        $request = Request::create($uri);
        $request->attributes->set('_route', $route);

        return $request;
    }

    private function event(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
