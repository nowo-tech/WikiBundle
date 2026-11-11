<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Twig;

use DateTimeImmutable;
use DateTimeZone;
use Nowo\MilkdownEditorBundle\Markdown\MarkdownRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Twig\MilkdownEditorTwig;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownIconHydrator;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownRichContent;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Twig\WikiHtmlTwigExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
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

    /** 1.31: nowo_wiki_datetime formats in the given locale (escaped text), the fixed format without one. */
    public function testTheDatetimeFilter(): void
    {
        $twig = new Environment(new ArrayLoader(['t' => '{{ date|nowo_wiki_datetime(locale) }}']));
        $twig->addExtension(new WikiHtmlTwigExtension(new WikiHtmlSanitizer()));
        $date = new DateTimeImmutable('2026-11-11 21:40', new DateTimeZone('UTC'));

        self::assertSame('Nov 11, 2026, 9:40 PM', $twig->render('t', ['date' => $date, 'locale' => 'en']));
        self::assertSame('2026-11-11 21:40', $twig->render('t', ['date' => $date, 'locale' => null]));
    }

    public function testFilterReturnsEmptyStringForEmptyInput(): void
    {
        $extension = new WikiHtmlTwigExtension(new WikiHtmlSanitizer());

        self::assertSame('', $extension->sanitize(null));
        self::assertSame('', $extension->sanitize(''));
    }

    public function testMilkdownIconsAreDrawnAfterSanitizingAndAssetsArePrinted(): void
    {
        $packages  = new Packages(new Package(new EmptyVersionStrategy()), ['nowo_milkdown_editor' => new Package(new EmptyVersionStrategy())]);
        $extension = new WikiHtmlTwigExtension(
            new WikiHtmlSanitizer([], [], ['data-icon', 'role', 'aria-label']),
            new WikiMilkdownIconHydrator(new AllowlistIconResolver('/s.svg', ['home'])),
            new WikiMilkdownRichContent(new MilkdownEditorTwig($this->createStub(MarkdownRendererInterface::class), $packages)),
        );
        $twig = new Environment(new ArrayLoader(['t' => '{{ html|nowo_wiki_safe_html }}|{{ nowo_wiki_rich_content_assets() }}']));
        $twig->addExtension($extension);

        $output = $twig->render('t', ['html' => '<p><span class="nowo-md-icon" data-icon="home" role="img" aria-label="home"></span><svg><script>x</script></svg></p>']);

        self::assertStringStartsWith('<p><svg class="nowo-md-icon" role="img" aria-label="home"><use href="/s.svg#tabler-home"></use></svg></p>|<link rel="stylesheet"', $output);
        self::assertStringContainsString('<script type="module"', $output);
    }

    public function testNoRichContentAssetsWithoutMilkdown(): void
    {
        self::assertSame('', (new WikiHtmlTwigExtension(new WikiHtmlSanitizer()))->richContentAssets());
    }
}
