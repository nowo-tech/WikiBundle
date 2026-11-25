<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Interchange\WikiMarkdownConverter;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownPageResolver;
use Nowo\WikiBundle\Markdown\Milkdown\WikiMilkdownSectionResolver;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiViewerMarkdownView;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Port\Internal\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageTitleFinderInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_column;

/**
 * 1.40 (contract §1 Q1, §4; W1 + W3): explicit heading ids, links to a section (`#nowo-h-<id>` with the section's text in
 * the label), the reader's Markdown keeping anchors and differing ids, and hidden pages never showing a section.
 */
final class SectionAnchorsRenderTest extends TestCase
{
    private WikiSpace $space;

    private WikiPage $page;

    private WikiPage $target;

    private WikiPage $hidden;

    protected function setUp(): void
    {
        $this->space  = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 'team-1');
        $this->page   = new WikiPage($this->space, 'runbook', 'Runbook');
        $this->target = new WikiPage($this->space, 'guide', 'Guide');
        $this->hidden = new WikiPage($this->space, 'secret', 'Secret plans');
        $this->revise($this->target, '<h2 id="nowo-h-setup">Set up <a class="nowo-md-ref" href="/x">Other</a></h2><div><h2 id="nowo-h-nested">Nested</h2></div>');
        $this->revise($this->hidden, '<h2 id="nowo-h-plan">The plan</h2>');
    }

    public function testExplicitIdsWinAndDuplicatesGetTheNextFreeSuffix(): void
    {
        [$renderer] = $this->renderer();
        $html       = $renderer->render("## Install guide {#setup}\n\n## Intro\n\n## Intro\n\n## Other {#intro}\n\n## Escaped \\{#x}");

        self::assertStringContainsString('<h2 id="nowo-h-setup">Install guide</h2>', $html);
        self::assertStringContainsString('<h2 id="nowo-h-intro-2">Intro</h2>', $html);
        self::assertStringContainsString('<h2 id="nowo-h-intro-3">Intro</h2>', $html);
        self::assertStringContainsString('<h2 id="nowo-h-intro">Other</h2>', $html);
        self::assertStringContainsString('{#x}', $html);
    }

    public function testALinkToASectionPointsAtTheHeadingWithTheSectionText(): void
    {
        [$renderer, $context] = $this->renderer();
        $source               = 'See [[wiki:' . $this->target->getId() . '#setup]], [[wiki:' . $this->target->getId() . '#nested]], [[wiki:' . $this->target->getId() . '#gone]] and [[wiki:' . $this->hidden->getId() . '#plan]].';

        $html = $context->within($this->space, $this->page, static fn (): string => $renderer->renderForViewer($source));

        self::assertStringContainsString('href="/wiki/eng/guide#nowo-h-setup">Guide › Set up</a>', $html);
        // A nested heading (not in the outline) and a missing section read the page title alone, never the raw id.
        self::assertStringContainsString('href="/wiki/eng/guide#nowo-h-nested">Guide</a>', $html);
        self::assertStringContainsString('href="/wiki/eng/guide#nowo-h-gone">Guide</a>', $html);
        self::assertStringNotContainsString('plan', $html);
        self::assertStringNotContainsString('Secret', $html);
    }

    public function testTheReadersMarkdownKeepsAnchorsAndDifferingIds(): void
    {
        [$renderer, $context] = $this->renderer();
        $view                 = new WikiViewerMarkdownView($renderer, new WikiHtmlSanitizer(), new WikiMarkdownConverter(true), $context);
        $source               = "## Install guide {#setup}\n\n## Plain\n\nSee [[wiki:" . $this->target->getId() . '#setup]], [[wiki:' . $this->hidden->getId() . "#plan]] and [x](/wiki/eng/guide#top).\n\n[docs](https://example.com/a#b)";

        $reader = $view->view($this->space, $this->page, $source);

        self::assertStringContainsString('## Install guide {#setup}', $reader['markdown']);
        self::assertStringContainsString("## Plain\n", $reader['markdown']);
        self::assertStringNotContainsString('{#plain}', $reader['markdown']);
        self::assertStringContainsString('[[wiki:' . $this->target->getId() . '#setup]]', $reader['markdown']);
        self::assertStringNotContainsString($this->hidden->getId(), $reader['markdown']);
        self::assertStringContainsString('(/wiki/eng/guide#top)', $reader['markdown']);
        self::assertStringContainsString('[docs](https://example.com/a#b)', $reader['markdown']);
        self::assertSame(['setup', 'plain'], array_column($reader['sections'], 'id'));
        self::assertSame($reader['markdown'], $view->markdown($this->space, $this->page, $source));
        self::assertSame(['markdown' => '', 'sections' => []], $view->view($this->space, $this->page, ' '));
        // Audit RY-L1: a heading with a page link keeps its implicit id, so no suffix is written.
        self::assertStringNotContainsString('{#', $view->markdown($this->space, $this->page, '## See [[wiki:' . $this->target->getId() . ']] now'));
    }

    public function testTheSectionResolverAsksOnlyAboutPagesResolvedInTheFrame(): void
    {
        $context  = new WikiRenderContext();
        $resolver = new WikiMilkdownSectionResolver($this->repository(true), $context);
        $anchors  = [$this->target->getId() => ['setup', 'nested'], $this->hidden->getId() => ['plan']];

        // Outside a frame: the renderer only ever asks about resolved pages.
        self::assertSame([$this->target->getId() => ['setup' => 'Set up'], $this->hidden->getId() => ['plan' => 'The plan']], $resolver->sectionTitles($anchors));

        $inFrame = $context->within($this->space, $this->page, function (WikiRenderFrame $frame) use ($resolver, $anchors): array {
            $frame->recordPageLink('/wiki/eng/guide', $this->target->getId());

            return $resolver->sectionTitles($anchors);
        });
        self::assertSame([$this->target->getId() => ['setup' => 'Set up']], $inFrame);
        self::assertSame([], $context->within($this->space, $this->page, static fn (): array => $resolver->sectionTitles($anchors)));
        self::assertSame([], $resolver->sectionTitles([]));
    }

    public function testTheSectionResolverWithAHostRepository(): void
    {
        $resolver = new WikiMilkdownSectionResolver($this->repository(false));
        $empty    = new WikiPage($this->space, 'empty', 'Empty');

        self::assertSame([$this->target->getId() => ['setup' => 'Set up']], $resolver->sectionTitles([$this->target->getId() => ['setup'], $empty->getId() => ['x'], 'missing' => ['y']]));
    }

    /**
     * @return array{0: MilkdownWikiMarkdownRenderer, 1: WikiRenderContext}
     */
    private function renderer(): array
    {
        $repository = $this->repository(true);
        $hiddenId   = $this->hidden->getId();
        $access     = $this->createStub(WikiSpaceAccessCheckerInterface::class);
        $access->method('isGranted')->willReturnCallback(static fn (UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool => $page?->getId() !== $hiddenId);
        $checker = $this->createStub(WikiAccessCheckerInterface::class);
        $checker->method('canList')->willReturn(true);
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new TestUser(), 'main', ['ROLE_USER']));
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $name, array $params): string => '/wiki/' . $params['spaceSlug'] . '/' . $params['pageSlug']);
        $context  = new WikiRenderContext();
        $resolver = new WikiMilkdownPageResolver($repository, $access, $checker, $tokens, $urls, ['page_view' => ['name' => 'nowo_wiki_page_view']], null, $context);

        return [new MilkdownWikiMarkdownRenderer([], $resolver, new NullUserResolver(), new AllowlistIconResolver('/icons/tabler.svg', ['home']), sections: new WikiMilkdownSectionResolver($repository, $context)), $context];
    }

    private function repository(bool $finder): WikiPageRepositoryInterface
    {
        $pages = [];
        foreach ([$this->page, $this->target, $this->hidden] as $page) {
            $pages[$page->getId()] = $page;
        }
        $repository = $finder ? $this->createStubForIntersectionOfInterfaces([WikiPageRepositoryInterface::class, WikiPageTitleFinderInterface::class]) : $this->createStub(WikiPageRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(static fn (string $id): ?WikiPage => $pages[$id] ?? null);
        if ($repository instanceof WikiPageTitleFinderInterface) {
            $repository->method('currentHtml')->willReturnCallback(static function (array $ids) use ($pages): array {
                $html = [];
                foreach ($ids as $id) {
                    $revision = isset($pages[$id]) ? $pages[$id]->getCurrentRevision() : null;
                    if ($revision instanceof WikiPageRevision) {
                        $html[$id] = $revision->getContentHtml();
                    }
                }

                return $html;
            });
        }

        return $repository;
    }

    private function revise(WikiPage $page, string $html): void
    {
        $page->setCurrentRevision(new WikiPageRevision($page, 1, $html, null));
    }
}
