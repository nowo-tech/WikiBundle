<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Ui;

use Nowo\UiKitBundle\View\Label;
use Nowo\WikiBundle\WikiBundle;
use Throwable;
use Twig\Environment;

use function in_array;
use function is_string;
use function trigger_deprecation;

/**
 * Page plumbing of the UiKit page contract (REQ-UI-003): resolves the layout the wiki pages extend
 * (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`), wraps a pre-1.10 layout (one that renders
 * `{% block body %}` instead of the UiKit canonical blocks) in a deprecated bridge, and resolves the per-page template
 * overrides (`web_ui.templates.<page>`). Configuration only: no request state, safe in worker mode.
 */
final readonly class WikiPages
{
    /** Bundle name of the page view models (theme exceptions `nowo_ui_kit.theme.bundles.NowoWikiBundle`). */
    public const BUNDLE = 'NowoWikiBundle';

    /** Translation domain of the bundle labels (REQ-I18N-003). */
    public const DOMAIN = WikiBundle::TRANSLATION_DOMAIN;

    /** BC bridge around a pre-1.10 layout that renders `{% block body %}` (removed in 2.0). */
    public const LEGACY_ADMIN_BRIDGE = '@NowoWikiBundle/manage/_legacy_layout.html.twig';

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
    ];

    /** A layout that defines this block renders the UiKit canonical blocks. */
    private const CANONICAL_BLOCK = 'nowo_ui_page_header';

    /** Canonical blocks a pre-1.10 layout may already render (the bridge skips them). */
    private const LEGACY_HOST_BLOCKS = ['nowo_ui_flashes', 'nowo_ui_styles', 'nowo_ui_scripts'];

    /**
     * @param string|null $layoutTemplate `web_ui.layout_template` (null = the UiKit admin layout)
     * @param array<string, string|null> $templates `web_ui.templates` (page id => template; null = bundle template)
     * @param Environment|null $twig Twig, to tell a pre-1.10 layout from a canonical one (null = never bridged)
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
     * Layout the scaffolds extend (`page.layout`): `web_ui.layout_template`, the legacy bridge for a pre-1.10 layout,
     * else null (UiKit `nowo_ui_layout.admin`).
     */
    public function layout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout === null) {
            return null;
        }

        return $this->legacy($layout) !== null ? self::LEGACY_ADMIN_BRIDGE : $layout;
    }

    /**
     * Shared variables of the wiki pages (added to the controller variables): the pre-1.10 layout wrapped by the
     * deprecated bridge and the canonical blocks it already renders.
     *
     * @param array<string, mixed> $vars page variables
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        $layout = $this->configuredLayout();
        $legacy = $layout !== null ? $this->legacy($layout, false) : null;

        return $vars + [
            'nowo_wiki_legacy_layout' => $legacy,
            'nowo_wiki_legacy_blocks' => $this->hostBlocks($legacy),
        ];
    }

    private function configuredLayout(): ?string
    {
        if (in_array($this->layoutTemplate, [null, '', self::LEGACY_DEFAULT_LAYOUT], true)) {
            return null;
        }

        return $this->layoutTemplate;
    }

    /**
     * Canonical blocks a pre-1.10 layout defines itself (the bridge does not render them a second time).
     *
     * @return list<string>
     */
    private function hostBlocks(?string $legacy): array
    {
        if ($legacy === null || !$this->twig instanceof Environment) {
            return [];
        }

        $template = $this->twig->load($legacy);
        $context  = $this->twig->mergeGlobals([]);
        $blocks   = [];
        foreach (self::LEGACY_HOST_BLOCKS as $block) {
            if ($template->hasBlock($block, $context)) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * The configured layout when it is a pre-1.10 layout (it does not define the UiKit canonical blocks), else null.
     */
    private function legacy(string $layout, bool $deprecate = true): ?string
    {
        if (!$this->twig instanceof Environment) {
            return null;
        }

        try {
            $canonical = $this->twig->load($layout)->hasBlock(self::CANONICAL_BLOCK, $this->twig->mergeGlobals([]));
        } catch (Throwable) {
            return null;
        }

        if ($canonical) {
            return null;
        }

        if ($deprecate) {
            trigger_deprecation('nowo-tech/wiki-bundle', '1.10', 'The layout "%s" does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...); it is wrapped in a deprecated bridge. Render the canonical blocks (or extend @NowoUiKit/layout/admin.html.twig) or set nowo_wiki.web_ui.layout_template to null to use nowo_ui_kit.layouts.admin; see docs/UPGRADING.md.', $layout);
        }

        return $layout;
    }
}
