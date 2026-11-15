<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Contract;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\WikiBundle\Dto\WikiPageFormData;
use Nowo\WikiBundle\Form\WikiPageFormType;
use Nowo\WikiBundle\Service\WikiPageTreeBuilder;
use Nowo\WikiBundle\Tests\Support\PageFixtures;
use Nowo\WikiBundle\Ui\WikiPageFactory;
use Nowo\WikiBundle\Ui\WikiPages;
use Twig\TwigFunction;

use function htmlspecialchars;
use function preg_match;
use function substr_count;

use const ENT_QUOTES;

/**
 * 1.38: the page icon in the read view (before the card title, or the card's first line), in both page trees (the
 * nested list and the UiKit tree's leading template) and in the page form (page_icon.form_field); decorative and
 * escaped.
 */
final class PageIconContractTest extends PageContractTestCase
{
    use WikiContractEnvironment;

    private const VIEW = '@NowoWikiBundle/manage/page_view.html.twig';

    public static function pageFixtures(): iterable
    {
        $context              = PageFixtures::pageView(true, null, false);
        $context['page_icon'] = 'tabler:rocket';
        yield 'page view (page icon)' => new PageFixture(self::VIEW, $context);
    }

    public function testThePageViewAndTheNestedTreeShowTheIcon(): void
    {
        $context              = PageFixtures::pageView(true, null, false);
        $context['page_icon'] = 'tabler:rocket';
        $context['tree']      = $this->treeWithIcon();
        $html                 = $this->renderTabler($context);

        self::assertSame(1, preg_match('#<div class="nowo-wiki-page-icon-line" data-testid="wiki-page-icon"><span class="nowo-wiki-page-icon nowo-wiki-page-icon--title" aria-hidden="true">.+?</span></div>#s', $html));
        self::assertSame(1, preg_match('#<a href="[^"]*pageSlug=welcome[^"]*" class="nowo-wiki-tree__link"><span class="nowo-wiki-page-icon" aria-hidden="true">.+?</span>Welcome</a>#s', $html));
        self::assertSame(2, substr_count($html, 'nowo-wiki-page-icon"') + substr_count($html, 'nowo-wiki-page-icon nowo-wiki-page-icon--title"'));

        // Without an icon: nothing.
        $plain = $this->renderPage(new PageFixture(self::VIEW, PageFixtures::pageView(true, null, false)))->html;
        self::assertStringNotContainsString('nowo-wiki-page-icon', $plain);
    }

    public function testTheCardTitleCarriesTheIcon(): void
    {
        $context                     = PageFixtures::pageView(true, null, false);
        $context['page_icon']        = 'tabler:rocket';
        $context['title_in_card']    = true;
        $context['nowo_wiki_web_ui'] = ['layout' => ['toolbar_position' => 'card', 'tabs' => 'toolbar']];
        $html                        = $this->renderTabler($context);

        self::assertSame(1, preg_match('#<h1 class="nowo-wiki-page-title" data-testid="wiki-page-title"><span class="nowo-wiki-page-icon nowo-wiki-page-icon--title" aria-hidden="true">.*?</span>Deploy</h1>#s', $html));
        self::assertStringNotContainsString('data-testid="wiki-page-icon"', $html);
    }

    public function testTheUiKitTreeDrawsTheIconBeforeTheLabel(): void
    {
        $factory            = PageFixtures::factory();
        $context            = PageFixtures::pageView(true, null, false);
        $context['tree']    = $this->treeWithIcon();
        $context['tree_ui'] = ['nodes' => $factory->uiTree($context['tree'], []), 'attr' => ['id' => 'nowo-wiki-tree'], 'movable' => false];
        $html               = $this->renderTabler($context);

        self::assertSame(1, preg_match('#role="treeitem"[^>]*>\s*<span class="nowo-wiki-page-icon" aria-hidden="true">.+?</span>\s*<span class="nowo-ui-tree__label">Welcome</span>#s', $html));
        // The icon name is never printed as the UiKit tree's plain-text icon.
        self::assertStringNotContainsString('nowo-ui-tree__icon', $html);
    }

    public function testAnIconTheIconSetCannotDrawPrintsNothing(): void
    {
        // The contract environment draws bootstrap-icons: a tabler: name would be a broken class.
        $context              = PageFixtures::pageView(true, null, false);
        $context['page_icon'] = 'tabler:rocket';
        $html                 = $this->renderPage(new PageFixture(self::VIEW, $context))->html;
        self::assertStringNotContainsString('nowo-wiki-page-icon', $html);
        self::assertStringNotContainsString('bi-tabler', $html);
    }

    public function testAnIconValueIsEscaped(): void
    {
        $twig = $this->createTwig();
        $twig->addGlobal('nowo_ui_kit_icon_set', 'ux_icon');
        $twig->addFunction(new TwigFunction('ux_icon', static fn (string $name): string => '<svg data-icon="' . htmlspecialchars($name, ENT_QUOTES) . '"></svg>', ['is_safe' => ['html']]));
        $html = $twig->render('@NowoWikiBundle/manage/_page_icon.html.twig', ['icon' => 'tabler:"><script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
    }

    public function testThePageFormGetsTheIconFieldOnlyWhenAsked(): void
    {
        $factory = PageFixtures::formFactory();
        $with    = $factory->create(WikiPageFormType::class, new WikiPageFormData('T', '', null, 'tabler:rocket'), ['icon_field' => true])->createView();
        self::assertSame('tabler:rocket', $with->children['icon']->vars['value']);
        self::assertSame('wiki.page.icon', $with->children['icon']->vars['label']);
        self::assertSame('wiki.page.icon_help', $with->children['icon']->vars['help']);

        $without = $factory->create(WikiPageFormType::class, new WikiPageFormData('T'))->createView();
        self::assertArrayNotHasKey('icon', $without->children);
    }

    /**
     * Renders the page view with the tabler-icons set (the contract environment draws bootstrap-icons).
     *
     * @param array<string, mixed> $context
     */
    private function renderTabler(array $context): string
    {
        $twig = $this->createTwig();
        $twig->addGlobal('nowo_ui_kit_icon_set', 'tabler-icons');

        return $twig->render(self::VIEW, $context);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function treeWithIcon(): array
    {
        $factory = new WikiPageFactory(PageFixtures::urlGenerator(), PageFixtures::routes(), new WikiPages(), null);
        $space   = PageFixtures::space('engineering', 'Engineering');
        [$root]  = PageFixtures::pages($space);
        $root->setIcon('tabler:rocket');

        return $factory->tree($space, (new WikiPageTreeBuilder())->build([$root]));
    }
}
