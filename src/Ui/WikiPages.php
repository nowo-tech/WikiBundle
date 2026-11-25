<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use LogicException;
use Nowo\UiKitBundle\View\Label;
use Nowo\WikiBundle\WikiBundle;
use Throwable;
use Twig\Environment;

use function in_array;
use function is_string;
use function sprintf;

/**
 * Page plumbing of the UiKit page contract (REQ-UI-003): resolves the layout the wiki pages extend
 * (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`; 2.0: it must render the UiKit canonical blocks) and
 * resolves the per-page template overrides (`web_ui.templates.<page>`). Configuration only: no request state, safe in worker mode.
 */
final readonly class WikiPages
{
    /** Bundle name of the page view models (theme exceptions `nowo_ui_kit.theme.bundles.NowoWikiBundle`). */
    public const BUNDLE = 'NowoWikiBundle';

    /** Translation domain of the bundle labels (REQ-I18N-003). */
    public const DOMAIN = WikiBundle::TRANSLATION_DOMAIN;

    /** The 1.x default of `templates.layout`: the removed bundle layout, read as "no layout override". */
    public const LEGACY_DEFAULT_LAYOUT = '@NowoWikiBundle/layout.html.twig';

    /** Page ids accepted by `web_ui.templates` (page id => default template). */
    public const TEMPLATES = [
        'index'        => '@NowoWikiBundle/manage/index.html.twig',
        'space'        => '@NowoWikiBundle/manage/space.html.twig',
        'page_view'    => '@NowoWikiBundle/manage/page_view.html.twig',
        'page_edit'    => '@NowoWikiBundle/manage/page_edit.html.twig',
        'page_history' => '@NowoWikiBundle/manage/page_history.html.twig',
        'page_diff'    => '@NowoWikiBundle/manage/page_diff.html.twig',
        'search'       => '@NowoWikiBundle/manage/search.html.twig',
        'ai_ask'       => '@NowoWikiBundle/manage/ai_ask.html.twig',
        'page_move'    => '@NowoWikiBundle/manage/page_move.html.twig',
        'trash'        => '@NowoWikiBundle/manage/trash.html.twig',
        'page_version' => '@NowoWikiBundle/manage/page_version.html.twig',
        'trash_purge'  => '@NowoWikiBundle/manage/trash_purge.html.twig',
        // 1.53 (lazy dialogs): the Import & export dialog's own page (GET of space_import).
        'import_export' => '@NowoWikiBundle/manage/import_export.html.twig',
    ];

    /**
     * 1.21 `web_ui.slots`: parts of the pages a host template may replace (included with the page variables).
     * sidebar: the page tree card (space home, page view); space_intro: the search card of the space home;
     * page_header_extra: after the page header of the page view, before the tab row. 1.30 page_header_toolbar: host
     * buttons in the page header toolbar of the page view and the edit form (block wiki_page_header_toolbar).
     */
    public const SLOTS = ['sidebar', 'space_intro', 'page_header_extra', 'page_header_toolbar'];

    /** A layout that defines this block renders the UiKit canonical blocks. */
    private const CANONICAL_BLOCK = 'nowo_ui_page_header';

    /**
     * @param string|null $layoutTemplate `web_ui.layout_template` (null = the UiKit admin layout)
     * @param array<string, string|null> $templates `web_ui.templates` (page id => template; null = bundle template)
     * @param Environment|null $twig Twig, to refuse a pre-1.10 layout (null = never checked)
     */
    public function __construct(
        private ?string $layoutTemplate = null,
        private array $templates = [],
        private ?Environment $twig = null,
    ) {
    }

    /**
     * Translation key of the bundle domain as a UiKit label.
     *
     * @param array<string, mixed> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, self::DOMAIN, $params);
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        $override = $this->templates[$page] ?? null;

        return is_string($override) && $override !== '' ? $override : self::TEMPLATES[$page] ?? $page;
    }

    /**
     * Layout the scaffolds extend (`page.layout`): `web_ui.layout_template`, else null (UiKit `nowo_ui_layout.admin`).
     *
     * @throws LogicException 2.0: when the configured layout does not render the UiKit canonical blocks (a pre-1.10
     *                        layout; the deprecated bridge that wrapped it is removed)
     */
    public function layout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout === null) {
            return null;
        }
        if ($this->lacksCanonicalBlocks($layout)) {
            throw new LogicException(sprintf('The wiki layout "%s" (nowo_wiki.web_ui.layout_template) does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...). 2.0 removed the bridge that wrapped such a pre-1.10 layout: render the canonical blocks (or extend @NowoUiKit/layout/admin.html.twig), or set nowo_wiki.web_ui.layout_template to null to use nowo_ui_kit.layouts.admin. See UPGRADING "To 2.0.0".', $layout));
        }

        return $layout;
    }

    /**
     * Shared variables of the wiki pages (added to the controller variables). 2.0: none (the pre-1.10 layout bridge and
     * its variables are removed); the page variables are returned as they are.
     *
     * @param array<string, mixed> $vars page variables
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $vars;
    }

    private function configuredLayout(): ?string
    {
        if (in_array($this->layoutTemplate, [null, '', self::LEGACY_DEFAULT_LAYOUT], true)) {
            return null;
        }

        return $this->layoutTemplate;
    }

    /**
     * Whether the configured layout is a pre-1.10 layout (it loads, but does not define the UiKit canonical blocks).
     * A layout Twig cannot load is left to Twig's own error when the page renders.
     */
    private function lacksCanonicalBlocks(string $layout): bool
    {
        if (!$this->twig instanceof Environment) {
            return false;
        }

        try {
            return !$this->twig->load($layout)->hasBlock(self::CANONICAL_BLOCK, $this->twig->mergeGlobals([]));
        } catch (Throwable) {
            return false;
        }
    }
}
