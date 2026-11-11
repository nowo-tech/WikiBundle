<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Ui;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\WikiBundle\Ui\WikiTabActions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * 1.30 web_ui.page_header.tabs: toolbar — the page tabs as toolbar icon buttons.
 */
#[CoversClass(WikiTabActions::class)]
final class WikiTabActionsTest extends TestCase
{
    public function testThePageViewGetsEditAndHistory(): void
    {
        $actions = WikiTabActions::fromTabs(self::tabs('page'), 'tabler-icons');

        self::assertSame(['wiki-toolbar-edit', 'wiki-toolbar-history'], self::ids($actions));
        self::assertSame(['/edit', '/history'], array_map(static fn (Action $action): ?string => $action->href, $actions));
        // tabler-icons draws the tabler names without their prefix; icon-only buttons, the label is the name.
        self::assertSame(['pencil', 'history'], array_map(static fn (Action $action): ?string => $action->icon, $actions));
        self::assertSame(['icon', 'icon'], array_map(static fn (Action $action): ?string => $action->display, $actions));
        self::assertSame('Edit', $actions[0]->label);
        self::assertArrayNotHasKey('aria-current', $actions[0]->attributes);
    }

    public function testTheEditFormAndTheHistoryGetBackAndTheCurrentButton(): void
    {
        $edit = WikiTabActions::fromTabs(self::tabs('edit'));
        self::assertSame(['wiki-toolbar-back', 'wiki-toolbar-edit', 'wiki-toolbar-history'], self::ids($edit));
        self::assertSame('/page', $edit[0]->href);
        self::assertSame('page', $edit[1]->attributes['aria-current']);
        self::assertArrayNotHasKey('aria-current', $edit[2]->attributes);
        // bootstrap-icons cannot draw tabler names: the canonical kinds.
        self::assertSame(['prev', 'edit', 'restore'], array_map(static fn (Action $action): ?string => $action->icon, $edit));

        $history = WikiTabActions::fromTabs(self::tabs('history'), 'ux_icon');
        self::assertSame('page', $history[2]->attributes['aria-current']);
        // ux_icon draws any name as written.
        self::assertSame(['tabler:arrow-left', 'tabler:pencil', 'tabler:history'], array_map(static fn (Action $action): ?string => $action->icon, $history));
    }

    public function testIconsFollowTheTabIconsAndTheIconSet(): void
    {
        $tabs = new AreaNav([
            new AreaNavItem('Page', '/page', true, ['data-testid' => 'wiki-tab-page']),
            new AreaNavItem('Edit', '/edit', false, ['data-testid' => 'wiki-tab-edit'], 'bi:pencil-square'),
            new AreaNavItem('History', '/history', false, ['data-testid' => 'wiki-tab-history'], 'clock'),
        ]);

        self::assertSame(['pencil-square', 'clock'], array_map(static fn (Action $action): ?string => $action->icon, WikiTabActions::fromTabs($tabs, 'bootstrap-icons')));
        self::assertSame(['edit', 'clock'], array_map(static fn (Action $action): ?string => $action->icon, WikiTabActions::fromTabs($tabs, 'svg_inline')));

        // No tab icon (web_ui.tab_icons null): the default one.
        $bare = new AreaNav([new AreaNavItem('History', '/history', false, ['data-testid' => 'wiki-tab-history'])]);
        self::assertSame('history', WikiTabActions::fromTabs($bare, 'tabler-icons')[0]->icon);
    }

    public function testNoTabsNoButtons(): void
    {
        self::assertSame([], WikiTabActions::fromTabs(null));
    }

    private static function tabs(string $current): AreaNav
    {
        return new AreaNav([
            new AreaNavItem('Page', '/page', $current === 'page', ['data-testid' => 'wiki-tab-page'], 'tabler:file-text'),
            new AreaNavItem('Edit', '/edit', $current === 'edit', ['data-testid' => 'wiki-tab-edit'], 'tabler:pencil'),
            new AreaNavItem('History', '/history', $current === 'history', ['data-testid' => 'wiki-tab-history'], 'tabler:history'),
        ]);
    }

    /**
     * @param list<Action> $actions
     *
     * @return list<mixed>
     */
    private static function ids(array $actions): array
    {
        return array_map(static fn (Action $action): mixed => $action->attributes['data-testid'] ?? null, $actions);
    }
}
