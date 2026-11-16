<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Twig;

use Nowo\WikiBundle\Security\WikiHtmlSanitizerInterface;
use Nowo\WikiBundle\Twig\WikiHtmlTwigExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * 1.39: emoji icons print as escaped text; the picker script flag follows the configuration.
 */
final class WikiHtmlTwigExtensionIconTest extends TestCase
{
    public function testEmojiArePrintedAsEscapedTextOnly(): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '[{{ nowo_wiki_icon_emoji(v) }}]']), ['autoescape' => 'html']);
        $twig->addExtension($this->ext());

        self::assertSame('[🚀]', $twig->render('t', ['v' => 'emoji:🚀']));
        self::assertSame('[]', $twig->render('t', ['v' => 'emoji:<script>']));
        self::assertSame('[]', $twig->render('t', ['v' => 'tabler:home']));
        self::assertSame('[]', $twig->render('t', ['v' => null]));
    }

    public function testThePickerFlag(): void
    {
        self::assertFalse($this->ext()->usesIconPicker());
        self::assertFalse($this->ext(['page' => ['picker' => 'text'], 'space' => []])->usesIconPicker());
        self::assertTrue($this->ext(['page' => ['picker' => 'text'], 'space' => ['picker' => 'icon_selector']])->usesIconPicker());
    }

    /**
     * @param array<string, array{picker?: string}> $picker
     */
    private function ext(array $picker = []): WikiHtmlTwigExtension
    {
        return new WikiHtmlTwigExtension($this->createStub(WikiHtmlSanitizerInterface::class), iconPicker: $picker);
    }
}
