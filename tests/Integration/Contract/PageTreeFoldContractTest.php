<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Contract;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\WikiBundle\Tests\Support\PageFixtures;

use function preg_match;
use function preg_quote;
use function substr_count;

/**
 * 1.38: the nested page tree's expand / collapse buttons (tree.default_expanded): a hidden <button> per parent row with
 * aria-expanded and aria-controls naming its sub-list, the current path marked on its lists, the default and the space
 * on the root for wiki.js; every list rendered open (no JavaScript: everything visible).
 */
final class PageTreeFoldContractTest extends PageContractTestCase
{
    use WikiContractEnvironment;

    private const VIEW = '@NowoWikiBundle/manage/page_view.html.twig';

    public static function pageFixtures(): iterable
    {
        yield 'page view (nested tree)' => new PageFixture(self::VIEW, PageFixtures::pageView());
    }

    public function testParentRowsGetAHiddenButtonControllingTheirList(): void
    {
        $context = PageFixtures::pageView();
        $html    = $this->renderPage(new PageFixture(self::VIEW, $context))->html;
        /** @var list<array{id: string, title: string, children: list<array{id: string, title: string}>}> $tree */
        $tree    = $context['tree'];
        $welcome = $tree[0]['id'];
        $deploy  = $tree[0]['children'][0]['id'];

        self::assertStringContainsString('<ul class="nowo-wiki-tree" data-wiki-tree data-wiki-tree-plain data-wiki-tree-default="current_path" data-wiki-tree-space="engineering">', $html);
        self::assertSame(1, preg_match('#<button type="button" class="nowo-ui-tree__toggle nowo-wiki-tree__toggle" aria-expanded="true" aria-controls="nowo-wiki-tree-group-' . preg_quote($welcome, '#') . '" data-wiki-tree-fold="' . preg_quote($welcome, '#') . '" hidden><span class="nowo-wiki-sr-only visually-hidden">Sub-pages of Welcome</span></button>#', $html));
        // Welcome is on the current page's path (Deploy), Deploy's own list (Rollback) is not.
        self::assertStringContainsString('<ul class="nowo-wiki-tree nowo-wiki-tree--nested" id="nowo-wiki-tree-group-' . $welcome . '" data-wiki-tree-group data-wiki-tree-current-path>', $html);
        self::assertStringContainsString('<ul class="nowo-wiki-tree nowo-wiki-tree--nested" id="nowo-wiki-tree-group-' . $deploy . '" data-wiki-tree-group>', $html);
        // Two parent rows, two buttons; no list is hidden by the server.
        self::assertSame(2, substr_count($html, 'data-wiki-tree-fold='));
        self::assertSame(0, preg_match('#data-wiki-tree-group[^>]*hidden#', $html));
        // The leaf (Glossary) has no button.
        self::assertSame(0, preg_match('#data-wiki-tree-fold="[^"]*"[^<]*<span[^>]*>Sub-pages of Glossary#', $html));
    }

    public function testTheDefaultComesFromTheConfiguration(): void
    {
        // The Twig global nowo_wiki_web_ui (WikiExtension: tree.default_expanded), also inside an include without context.
        foreach (['all', 'none'] as $mode) {
            $twig = $this->createTwig();
            $twig->addGlobal('nowo_wiki_web_ui', ['tree_default_expanded' => $mode]);
            $html = $twig->render('@NowoWikiBundle/manage/_page_tree.html.twig', ['nodes' => PageFixtures::pageView()['tree']]);
            self::assertStringContainsString('data-wiki-tree-default="' . $mode . '"', $html);
        }
    }

    public function testAnEmptyTreeAndABareNodeStillRender(): void
    {
        $html = $this->createTwig()->render('@NowoWikiBundle/manage/_page_tree.html.twig', ['nodes' => [['title' => 'Old <b>', 'href' => '/x', 'current' => false, 'children' => [['title' => 'Child', 'href' => '/y', 'current' => false, 'children' => []]]]]]);

        self::assertStringContainsString('data-wiki-tree-space=""', $html);
        self::assertStringContainsString('aria-controls="nowo-wiki-tree-group-"', $html);
        self::assertStringContainsString('Old &lt;b&gt;', $html);
        self::assertStringNotContainsString('data-wiki-tree-current-path', $html);
    }
}
