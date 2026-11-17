<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

use function dirname;
use function trim;

/**
 * 1.41: the focus mode toggle of the page toolbar (`_focus_toggle.html.twig`) — UiKit's `focus_mode_toggle` partial
 * with the area "wiki" and, with focus mode enabled and a tree, the "Pages" drawer button; nothing at all with a UiKit
 * that has no focus mode (no partial, no global), so the 1.40 markup stays as it was.
 */
final class FocusToggleTemplateTest extends TestCase
{
    public function testAUiKitWithoutFocusModePrintsNothing(): void
    {
        self::assertSame('', $this->render(false, null, ['tree' => true]));
    }

    public function testFocusModeOffPrintsOnlyWhatTheUiKitPartialPrints(): void
    {
        // UiKit >= 2.54 with focus_mode.enabled false: its partial prints nothing, the wiki prints no drawer button.
        self::assertSame('', $this->render(true, ['enabled' => false], ['tree' => true]));
    }

    public function testFocusModeOnPrintsTheToggleOfTheWikiAreaAndTheDrawerButton(): void
    {
        $html = $this->render(true, ['enabled' => true], ['tree' => true, 'bundle' => 'wiki']);
        self::assertStringContainsString('<button data-nowo-ui-focus-mode data-nowo-ui-focus-area="wiki" data-bundle="wiki"></button>', $html);
        self::assertStringContainsString('class="btn-secondary:wiki nowo-wiki-focus-tree-toggle" aria-expanded="false" data-wiki-focus-tree-toggle', $html);
        self::assertStringContainsString('>wiki.focus.tree</button>', $html);

        // Without a tree (the edit form's own layout): the toggle only.
        $bare = $this->render(true, ['enabled' => true], ['tree' => false]);
        self::assertStringContainsString('data-nowo-ui-focus-mode', $bare);
        self::assertStringNotContainsString('data-wiki-focus-tree-toggle', $bare);
    }

    /**
     * @param array<string, mixed>|null $focus the `nowo_ui_kit_focus_mode` global (null: a UiKit without it)
     * @param array<string, mixed> $vars
     */
    private function render(bool $partial, ?array $focus, array $vars): string
    {
        $views = new FilesystemLoader();
        $views->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'NowoWikiBundle');
        $uikit = new ArrayLoader($partial ? [
            '@NowoUiKitBundle/partials/_focus_mode_toggle.html.twig' => '{% if (nowo_ui_kit_focus_mode.enabled ?? false) is same as(true) %}<button data-nowo-ui-focus-mode data-nowo-ui-focus-area="{{ area }}" data-bundle="{{ bundle }}"></button>{% endif %}',
        ] : []);
        $twig = new Environment(new ChainLoader([$views, $uikit]), ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addFunction(new TwigFunction('nowo_ui_class', static fn (string $slot, ?string $bundle = null): string => $slot === 'btn_secondary' ? 'btn-secondary:' . $bundle : $slot));
        if ($focus !== null) {
            $twig->addGlobal('nowo_ui_kit_focus_mode', $focus);
        }

        return trim($twig->render('@NowoWikiBundle/manage/_focus_toggle.html.twig', $vars));
    }
}
