<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

use function dirname;

/**
 * Flash messages set by WikiManageController (import errors/reports) must be rendered by the bundle templates.
 */
final class FlashesTemplateTest extends TestCase
{
    private function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(dirname(__DIR__, 3) . '/src/Resources/views', 'NowoWikiBundle');

        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['wiki.import.missing_file' => 'Please choose a ZIP file to import.'], 'en', 'NowoWikiBundle');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));

        return $twig;
    }

    public function testRendersTranslatedAndRawFlashesWithAlertClasses(): void
    {
        $html = $this->twig()->render('@NowoWikiBundle/manage/_flashes.html.twig', [
            'app' => ['flashes' => [
                'error'   => ['wiki.import.missing_file'],
                'info'    => ['1 created, 0 updated, 0 skipped, 0 failed.'],
                'warning' => ['<script>x</script>'],
            ]],
        ]);

        self::assertStringContainsString('alert alert-danger', $html);
        self::assertStringContainsString('Please choose a ZIP file to import.', $html);
        self::assertStringContainsString('alert alert-info', $html);
        self::assertStringContainsString('1 created, 0 updated, 0 skipped, 0 failed.', $html);
        self::assertStringContainsString('alert alert-warning', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('role="status"', $html);
    }

    public function testRendersNothingWithoutFlashes(): void
    {
        $html = $this->twig()->render('@NowoWikiBundle/manage/_flashes.html.twig', ['app' => ['flashes' => []]]);

        self::assertSame('', trim($html));
    }

    public function testPageHeaderAndPageViewIncludeTheFlashesPartial(): void
    {
        $views = dirname(__DIR__, 3) . '/src/Resources/views/manage/';

        foreach (['_page_header.html.twig', 'page_view.html.twig'] as $template) {
            self::assertStringContainsString(
                "include('@NowoWikiBundle/manage/_flashes.html.twig')",
                (string) file_get_contents($views . $template),
                $template,
            );
        }
    }
}
