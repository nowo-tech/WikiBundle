<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Nowo\WikiBundle\Markdown\WikiMarkdownExtensionInterface;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use PHPUnit\Framework\TestCase;

final class WikiMarkdownRendererTest extends TestCase
{
    public function testRendersGithubFlavouredMarkdown(): void
    {
        $html = (new WikiMarkdownRenderer())->render(<<<'MD'
| Service | Port |
|---------|-----:|
| api     | 8080 |

- [x] shipped
- [ ] pending

~~gone~~ and https://example.com
MD);

        self::assertStringContainsString('<thead>', $html);
        self::assertStringContainsString('<td align="right">8080</td>', $html);
        self::assertStringContainsString('<ul data-type="taskList">', $html);
        self::assertStringContainsString('<li data-type="taskItem" data-checked="true">shipped</li>', $html);
        self::assertStringContainsString('<li data-type="taskItem" data-checked="false">pending</li>', $html);
        self::assertStringNotContainsString('<input', $html);
        self::assertStringContainsString('<del>gone</del>', $html);
        self::assertStringContainsString('<a href="https://example.com">https://example.com</a>', $html);
    }

    public function testEscapesRawHtmlAndDropsUnsafeLinks(): void
    {
        $html = (new WikiMarkdownRenderer())->render("<img src=x onerror=alert(1)>\n\n[click](javascript:alert(1)) [data](data:text/html,x)");

        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('data:text', $html);
    }

    public function testEmptyInputRendersNothing(): void
    {
        self::assertSame('', (new WikiMarkdownRenderer())->render("  \n "));
    }

    public function testHostExtensionsAddSyntax(): void
    {
        $extension = new class implements WikiMarkdownExtensionInterface {
            public function register(EnvironmentBuilderInterface $environment): void
            {
                $environment->addInlineParser(new class implements InlineParserInterface {
                    public function getMatchDefinition(): InlineParserMatch
                    {
                        return InlineParserMatch::string(':wave:');
                    }

                    public function parse(InlineParserContext $inlineContext): bool
                    {
                        $inlineContext->getCursor()->advanceBy(6);
                        $inlineContext->getContainer()->appendChild(new Text('👋'));

                        return true;
                    }
                });
            }
        };

        self::assertSame('<p>Hi 👋</p>', (new WikiMarkdownRenderer([$extension]))->render('Hi :wave:'));
        self::assertSame('<p>Hi :wave:</p>', (new WikiMarkdownRenderer())->render('Hi :wave:'));
    }
}
