<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Twig;

use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Twig\WikiHtmlTwigExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class WikiHtmlTwigExtensionTest extends TestCase
{
    public function testFilterSanitizesStoredHtmlAtRenderTime(): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{{ html|nowo_wiki_safe_html }}']));
        $twig->addExtension(new WikiHtmlTwigExtension(new WikiHtmlSanitizer()));

        $output = $twig->render('t', ['html' => '<p>ok</p><img src=x onerror=alert(1)>']);

        self::assertSame('<p>ok</p><img src="x" />', $output);
    }

    public function testFilterReturnsEmptyStringForEmptyInput(): void
    {
        $extension = new WikiHtmlTwigExtension(new WikiHtmlSanitizer());

        self::assertSame('', $extension->sanitize(null));
        self::assertSame('', $extension->sanitize(''));
    }
}
