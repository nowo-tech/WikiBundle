<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Label;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use PHPUnit\Framework\TestCase;

use function array_map;
use function is_scalar;

/**
 * View models of the wiki pages: actions per permission, value-free rows, optional routes.
 */
final class WikiPageFactoryTest extends TestCase
{
    public function testHeaderActionsFollowThePermissionsAndTheDashboardRoute(): void
    {
        $factory  = PageFixtures::factory(null, 'app_dashboard');
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);

        $full = $factory->space($space, ['create' => true, 'import' => true, 'export' => true]);
        self::assertSame(['create', 'export', 'export', 'prev'], $this->kinds($full->header->actions));
        self::assertSame('/app_dashboard', $full->header->actions[3]->href);

        self::assertSame(['prev'], $this->kinds($factory->space($space, ['create' => false, 'import' => false, 'export' => false])->header->actions));
        self::assertSame([], $this->kinds($factory->pageView($space, $page, ['edit' => false, 'history' => false])->header->actions));
        self::assertSame(['edit', 'view', 'delete'], $this->kinds($factory->pageView($space, $page, ['edit' => true, 'history' => true], PageFixtures::postForm('t'))->header->actions));
        self::assertSame([], $this->kinds((new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), ''))->search(PageFixtures::searchForm(null), [], '', false)->header->actions));
    }

    public function testRowsAreValueFree(): void
    {
        $factory = PageFixtures::factory();
        $index   = PageFixtures::index()['page'];
        $search  = PageFixtures::search()['page'];
        $history = PageFixtures::history()['page'];

        foreach ([$index, $search, $history] as $list) {
            foreach ($list->rows as $row) {
                self::assertIsArray($row);
                foreach ($row as $value) {
                    self::assertTrue($value === null || is_scalar($value), 'rows hold scalars only');
                }
            }
        }

        self::assertSame(['id' => 'engineering', 'name' => 'Engineering', 'slug' => 'engineering', 'href' => '/nowo_wiki_space?spaceSlug=engineering'], $index->rows[0]);
        self::assertSame([['title' => 'Welcome', 'href' => '/nowo_wiki_page_view?spaceSlug=engineering&pageSlug=welcome', 'current' => false, 'children' => []]], $factory->tree(PageFixtures::space('engineering', 'Engineering'), [['page' => new WikiPage(PageFixtures::space('engineering', 'E'), 'welcome', 'Welcome'), 'children' => 'not-a-list']]));
    }

    public function testRoutesThatAreNotConfiguredGiveNoActionOrLink(): void
    {
        $factory  = new WikiPageFactory(PageFixtures::urlGenerator(), ['space' => ['path' => '/s', 'name' => 'space'], 'page_view' => ['path' => '/v', 'name' => 'view']]);
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);

        self::assertSame([], $factory->index([], PageFixtures::index()['page']->pagination, PageFixtures::searchForm(null), true)->header->actions);
        self::assertSame([], $factory->space($space, ['create' => true, 'import' => true, 'export' => true])->header->actions);
        self::assertSame([], $factory->pageView($space, $page, ['edit' => true, 'history' => true], PageFixtures::postForm('t'))->header->actions);
        self::assertNull($factory->diff($space, $page, 1, 2)->header->back);
        self::assertNull($factory->pageView($space, $page, ['edit' => false, 'history' => false])->header->breadcrumbs[0]->href);
        self::assertSame([['title' => 'Deploy', 'action' => null]], $factory->archived($space, [$page], [$page->getId() => PageFixtures::postForm('t')]));

        $history = $factory->history($space, $page, [new WikiPageRevision($page, 2, '', new TestUser())], null, [2 => PageFixtures::postForm('t')], PageFixtures::index()['page']->pagination);
        self::assertSame([], $history->rowActions($history->rows[0]));
    }

    public function testHitsAndUsedInAreValueFree(): void
    {
        $factory  = PageFixtures::factory();
        $space    = PageFixtures::space('eng', 'Engineering');
        [, $page] = PageFixtures::pages($space);

        self::assertSame([['title' => 'Deploy', 'space' => 'Engineering', 'excerpt' => '…', 'href' => '/nowo_wiki_page_view?spaceSlug=eng&pageSlug=deploy']], $factory->hits([['page' => $page, 'space' => $space, 'excerpt' => '…']]));
        self::assertSame([['type' => 'ticket', 'label' => 'T-1', 'href' => '/t/1', 'badge' => null]], $factory->usedIn(['ticket' => [new WikiLinkedObject('ticket', '1', 'T-1', '/t/1')]]));
    }

    public function testLabelsUseTheBundleDomain(): void
    {
        $title = PageFixtures::index()['page']->header->title;

        self::assertInstanceOf(Label::class, $title);
        self::assertSame(WikiPages::DOMAIN, $title->domain);
        self::assertSame(WikiPages::TEMPLATES['index'], PageFixtures::factory()->template('index'));
    }

    /**
     * @param iterable<Action> $actions
     *
     * @return list<string>
     */
    private function kinds(iterable $actions): array
    {
        return array_map(static fn (Action $action): string => $action->kind, [...$actions]);
    }
}
