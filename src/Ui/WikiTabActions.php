<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\AreaNav;

use function explode;
use function str_contains;

/**
 * 1.30 `web_ui.page_header.tabs: toolbar`: the page tabs (Page / Edit / History, {@see WikiPageFactory::pageTabs()})
 * as icon buttons of the page header toolbar (UiKit `action` partial, display `icon`: the label is the accessible
 * name and the tooltip). On the page itself the Page tab is left out; on the edit form and the history it becomes
 * "Back to page". The current view's button keeps `aria-current="page"`. The icon follows the tab icon
 * (`web_ui.tab_icons`) when the UiKit icon set can draw it, else a canonical kind. Pure function, no state (worker
 * mode).
 *
 * 1.33 (`web_ui.page_header.edit_toolbar.back: view`): a Page tab carrying {@see self::BACK_VIEW_ATTRIBUTE} becomes a
 * "View page" button (eye icon) instead of "Back to page".
 */
final class WikiTabActions
{
    /** 1.33: attribute of the Page tab of the edit form that turns "Back to page" into "View page". */
    public const BACK_VIEW_ATTRIBUTE = 'data-nowo-wiki-back';

    /** Default icon of each button when its tab has none (`web_ui.tab_icons` set to null). */
    public const ICONS = [
        'back'    => 'tabler:arrow-left',
        'view'    => 'tabler:eye',
        'edit'    => 'tabler:pencil',
        'history' => 'tabler:history',
    ];

    /**
     * Canonical UiKit icon kind of each button, drawn by every icon set: used when the icon set cannot draw the tab
     * icon (a `tabler:` name under bootstrap-icons, as the UiKit `tabs` partial drops it).
     */
    public const FALLBACK_KINDS = [
        'back'    => 'prev',
        'view'    => 'view',
        'edit'    => 'edit',
        'history' => 'restore',
    ];

    /**
     * @param string $iconSet `nowo_ui_kit.icon_set` (ux_icon, tabler-icons, bootstrap-icons, svg_inline, none)
     *
     * @return list<Action>
     */
    public static function fromTabs(?AreaNav $tabs, string $iconSet = 'bootstrap-icons'): array
    {
        if (!$tabs instanceof AreaNav) {
            return [];
        }
        $actions = [];
        foreach ($tabs->items as $item) {
            $id = (string) ($item->attributes['data-testid'] ?? '');
            if ($id === 'wiki-tab-page') {
                // On the page itself the Page tab is not needed; elsewhere it is the way back.
                if (!$item->active) {
                    $actions[] = ($item->attributes[self::BACK_VIEW_ATTRIBUTE] ?? null) === 'view'
                        ? new Action('view', WikiPages::label('wiki.action.view_page'), $item->href, icon: self::icon(self::ICONS['view'], 'view', $iconSet), variant: 'neutral', attributes: ['data-testid' => 'wiki-toolbar-view'], display: 'icon')
                        : new Action('view', WikiPages::label('wiki.action.back_to_page'), $item->href, icon: self::icon(self::ICONS['back'], 'back', $iconSet), variant: 'neutral', attributes: ['data-testid' => 'wiki-toolbar-back'], display: 'icon');
                }

                continue;
            }
            $kind       = $id === 'wiki-tab-edit' ? 'edit' : 'history';
            $attributes = ['data-testid' => 'wiki-toolbar-' . $kind];
            if ($item->active) {
                $attributes['aria-current'] = 'page';
            }
            $actions[] = new Action($kind === 'edit' ? 'edit' : 'view', $item->label, $item->href, icon: self::icon($item->icon ?? self::ICONS[$kind], $kind, $iconSet), variant: 'neutral', attributes: $attributes, display: 'icon');
        }

        return $actions;
    }

    /**
     * The icon as the set draws it (the same rule as the UiKit `tabs` partial): ux_icon takes any name; tabler-icons a
     * `tabler:` name, bootstrap-icons a `bi:` name (without the prefix); a name without a prefix as written; anything
     * else the button's canonical kind.
     *
     * @param 'back'|'edit'|'history'|'view' $button
     */
    private static function icon(string $icon, string $button, string $iconSet): string
    {
        if (!str_contains($icon, ':') || $iconSet === 'ux_icon') {
            return $icon;
        }
        [$prefix, $name] = explode(':', $icon, 2);

        return ($iconSet === 'tabler-icons' && $prefix === 'tabler') || ($iconSet === 'bootstrap-icons' && $prefix === 'bi')
            ? $name
            : self::FALLBACK_KINDS[$button];
    }
}
