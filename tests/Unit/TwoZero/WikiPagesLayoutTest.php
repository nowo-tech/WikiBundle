<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\TwoZero;

use LogicException;
use Nowo\WikiBundle\Ui\WikiPages;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * 2.0: a layout without the UiKit canonical blocks (a pre-1.10 layout) is refused; the 1.10 bridge is gone.
 */
final class WikiPagesLayoutTest extends TestCase
{
    public function testAPre110LayoutIsRefusedAndACanonicalOneIsUsed(): void
    {
        $twig = new Environment(new ArrayLoader([
            'old.html.twig'       => '<html>{% block body %}{% endblock %}</html>',
            'canonical.html.twig' => '{% block nowo_ui_page_header %}{% endblock %}',
        ]));

        self::assertSame('canonical.html.twig', (new WikiPages('canonical.html.twig', [], $twig))->layout());
        self::assertSame('missing.html.twig', (new WikiPages('missing.html.twig', [], $twig))->layout(), 'Twig reports a missing layout itself');
        self::assertSame('old.html.twig', (new WikiPages('old.html.twig'))->layout(), 'without Twig nothing is checked');
        self::assertNull((new WikiPages(WikiPages::LEGACY_DEFAULT_LAYOUT, [], $twig))->layout());
        self::assertSame(['a' => 1], (new WikiPages())->context(['a' => 1]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The wiki layout "old.html.twig" (nowo_wiki.web_ui.layout_template) does not render the UiKit canonical blocks');
        (new WikiPages('old.html.twig', [], $twig))->layout();
    }
}
