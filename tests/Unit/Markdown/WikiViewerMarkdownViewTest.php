<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\Attachment\WikiAttachmentReferenceFilter;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownPageResolver;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderReach;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownView;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\InMemoryAttachmentRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 1.24 (spec 218 FR-013, SC-002): the reader's Markdown is built from the reader's render. Nothing the render hides —
 * ids and titles of pages the reader may not open, HTML comments, unused reference definitions, raw HTML — comes
 * through; pages the reader may open come back as `[[wiki:<uuid>]]`; the API's reach masks the rest.
 */
final class WikiViewerMarkdownViewTest extends TestCase
{
    private WikiSpace $space;

    private WikiSpace $restricted;

    private WikiPage $open;

    private WikiPage $hidden;

    private WikiPage $elsewhere;

    private WikiPage $page;

    protected function setUp(): void
    {
        $this->space      = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->restricted = new WikiSpace('hr', 'People', WikiSpaceOwnerScope::Team, 'team-1');
        $this->page       = new WikiPage($this->space, 'runbook', 'Runbook');
        $this->open       = new WikiPage($this->space, 'guide', 'Guide');
        $this->hidden     = new WikiPage($this->space, 'secret', 'Secret plans');
        $this->elsewhere  = new WikiPage($this->restricted, 'salaries', 'Salaries');
    }

    public function testHiddenThingsNeverComeThroughAndOpenPagesKeepTheirToken(): void
    {
        [$view] = $this->view();
        $source = "# Runbook\n\nSee [[wiki:" . $this->open->getId() . ']] and [[wiki:' . $this->hidden->getId() . "]].\n\n"
            . "<!-- reviewer: rotate the root password -->\n\n[unused]: https://internal.example/admin\n\n<b>raw</b>\n\n- [ ] check";

        $markdown = $view->markdown($this->space, $this->page, $source);

        self::assertStringContainsString('[[wiki:' . $this->open->getId() . ']]', $markdown);
        self::assertStringNotContainsString($this->hidden->getId(), $markdown);
        self::assertStringNotContainsString('Secret plans', $markdown);
        self::assertStringContainsString('Content not visible', $markdown);
        self::assertStringNotContainsString('internal.example', $markdown);
        self::assertStringStartsWith('# Runbook', $markdown);
        // With `html_input: escape` (the default) the page shows raw HTML as text, so the reader gets that text too.
        self::assertStringContainsString('&lt;!-- reviewer: rotate the root password --&gt;', $markdown);
    }

    public function testWithRawHtmlStrippedTheReaderGetsNoneOfIt(): void
    {
        [$view] = $this->view(['html_input' => 'strip']);

        $markdown = $view->markdown($this->space, $this->page, "Text\n\n<!-- reviewer: rotate the root password -->\n\n<b>raw</b> after");

        self::assertStringNotContainsString('rotate the root password', $markdown);
        self::assertStringNotContainsString('<b>', $markdown);
        self::assertStringContainsString('Text', $markdown);
    }

    public function testTheReachMasksPagesThePersonCouldOpen(): void
    {
        [$view] = $this->view();
        $source = 'See [[wiki:' . $this->open->getId() . ']] and [[wiki:' . $this->elsewhere->getId() . ']].';
        $only   = new class($this->space) implements WikiRenderReach {
            public function __construct(private readonly WikiSpace $space)
            {
            }

            public function reachesSpace(WikiSpace $space): bool
            {
                return $space === $this->space;
            }
        };

        $open    = $view->markdown($this->space, $this->page, $source);
        $reached = $view->markdown($this->space, $this->page, $source, $only);

        self::assertStringContainsString('[[wiki:' . $this->elsewhere->getId() . ']]', $open);
        self::assertStringNotContainsString($this->elsewhere->getId(), $reached);
        self::assertStringNotContainsString('Salaries', $reached);
        self::assertStringContainsString('[[wiki:' . $this->open->getId() . ']]', $reached);
    }

    public function testAnOrdinaryLinkStaysALink(): void
    {
        [$view] = $this->view();

        self::assertSame('[docs](https://example.com/docs)', $view->markdown($this->space, $this->page, '[docs](https://example.com/docs)'));
    }

    public function testEmptySourceAndTheNeutralRenderer(): void
    {
        [$view] = $this->view();
        self::assertSame('', $view->markdown($this->space, $this->page, " \n "));

        $neutral = new WikiViewerMarkdownView(new WikiMarkdownRenderer(), new WikiHtmlSanitizer(), new WikiMarkdownConverter(), new WikiRenderContext());
        self::assertSame('Plain **bold**', $neutral->markdown($this->space, $this->page, 'Plain **bold**'));
    }

    public function testAttachmentUrlsOfOtherSpacesAreDropped(): void
    {
        [, $renderer, $context] = $this->view();
        $view                   = new WikiViewerMarkdownView($renderer, new WikiHtmlSanitizer(), new WikiMarkdownConverter(true), $context, new WikiAttachmentReferenceFilter(new InMemoryAttachmentRepository()));

        self::assertStringNotContainsString('0b6f4f0e', $view->markdown($this->space, $this->page, '[file](/wiki/attachments/0b6f4f0e-3c55-4d0a-9a43-1f7a2c9e8d10)'));
    }

    /**
     * @param array<string, mixed> $options the Milkdown renderer options
     *
     * @return array{0: WikiViewerMarkdownView, 1: MilkdownWikiMarkdownRenderer, 2: WikiRenderContext}
     */
    private function view(array $options = []): array
    {
        $pages = [];
        foreach ([$this->page, $this->open, $this->hidden, $this->elsewhere] as $page) {
            $pages[$page->getId()] = $page;
        }
        $repository = $this->createStub(WikiPageRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(static fn (string $id): ?WikiPage => $pages[$id] ?? null);
        $hiddenId = $this->hidden->getId();
        $access   = $this->createStub(WikiSpaceAccessCheckerInterface::class);
        $access->method('isGranted')->willReturnCallback(static fn (UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool => $page?->getId() !== $hiddenId);
        $checker = $this->createStub(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn(true);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new TestUser(), 'main', ['ROLE_USER']));
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $name, array $params): string => '/wiki/' . $params['spaceSlug'] . '/' . $params['pageSlug'] . '?a=1&b=2');

        $context  = new WikiRenderContext();
        $resolver = new WikiMilkdownPageResolver($repository, $access, $checker, $tokens, $urls, ['page_view' => ['name' => 'nowo_wiki_page_view']], null, $context);
        $renderer = new MilkdownWikiMarkdownRenderer($options, $resolver, new NullUserResolver(), new AllowlistIconResolver('/icons/tabler.svg', ['home']));

        return [new WikiViewerMarkdownView($renderer, new WikiHtmlSanitizer(), new WikiMarkdownConverter(true), $context), $renderer, $context];
    }
}
