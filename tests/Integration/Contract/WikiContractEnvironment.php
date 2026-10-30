<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Contract;

use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Twig\WikiHtmlTwigExtension;
use Twig\Environment;
use Twig\TwigFunction;

use function dirname;

/**
 * Twig environment of the UiKit page contract test for the bundle: bundle views and catalogue, the real HTML
 * sanitizer filter, the TiptapEditorBundle asset functions, strict missing-translation rule.
 *
 * @phpstan-require-extends \Nowo\UiKitBundle\Test\PageContractTestCase
 */
trait WikiContractEnvironment
{
    protected static function templatePaths(): array
    {
        return ['NowoWikiBundle' => dirname(__DIR__, 3) . '/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 3) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 3) . '/docs/TEMPLATES.md';
    }

    protected static function strictMissingTranslations(): bool
    {
        return true;
    }

    protected function configureTwig(Environment $twig): void
    {
        $this->configureWikiTwig($twig);
    }

    /**
     * Wiki Twig extensions and functions (the real sanitizer filter, the TiptapEditorBundle asset functions).
     */
    protected function configureWikiTwig(Environment $twig): void
    {
        $twig->addExtension(new WikiHtmlTwigExtension(new WikiHtmlSanitizer()));
        $twig->addFunction(new TwigFunction('nowo_tiptap_editor_asset_path', static fn (string $path): string => 'tiptap/' . $path));
        $twig->addFunction(new TwigFunction('nowo_tiptap_editor_asset_package', static fn (): ?string => null));
    }
}
