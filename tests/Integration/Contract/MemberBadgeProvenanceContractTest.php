<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration\Contract;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\Pagination;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Member\WikiMemberBadge;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\PageFixtures;

use function is_array;
use function preg_match;
use function substr_count;

/**
 * Spec 234: the member badges and the write provenance in the history and the comments panel — UiKit page
 * contract (theme slots, translations), every host value escaped (XSS-safe by construction), and the same markup as
 * before when the hooks give nothing.
 */
final class MemberBadgeProvenanceContractTest extends PageContractTestCase
{
    use WikiContractEnvironment;

    private const SCRIPT = '<script>alert(1)</script>';

    public static function pageFixtures(): iterable
    {
        yield 'history (badges, provenance)' => new PageFixture('@NowoWikiBundle/manage/page_history.html.twig', self::history(true));
        yield 'page view (comment badges)' => new PageFixture('@NowoWikiBundle/manage/page_view.html.twig', self::pageView(true));
    }

    public function testTheHistoryShowsBadgesAndProvenanceEscaped(): void
    {
        $html = $this->createTwig()->render('@NowoWikiBundle/manage/page_history.html.twig', self::history(true));

        self::assertStringNotContainsString(self::SCRIPT, $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertSame(1, preg_match('#<span class="[^"]*nowo-wiki-member-badge nowo-wiki-member-badge--agent" title="Service &quot;account&quot;" data-testid="wiki-member-badge">Agent</span>#', $html));
        self::assertSame(1, preg_match('#data-testid="wiki-history-provenance">via MCP · <span class="nowo-wiki-provenance__actor">&lt;script&gt;alert\(1\)&lt;/script&gt; token</span></span>#', $html));
        // A row with a channel only, a row with a label only.
        self::assertSame(1, preg_match('#data-testid="wiki-history-provenance">via Web</span>#', $html));
        self::assertSame(1, preg_match('#data-testid="wiki-history-provenance"><span class="nowo-wiki-provenance__actor">Import job</span></span>#', $html));
        self::assertSame(1, substr_count($html, 'data-testid="wiki-member-badge"'), 'no badge beside a former person');
    }

    public function testWithoutTheHooksTheHistoryRendersAsBefore(): void
    {
        $html = $this->createTwig()->render('@NowoWikiBundle/manage/page_history.html.twig', self::history(false));

        self::assertStringNotContainsString('wiki-member-badge', $html);
        self::assertStringNotContainsString('wiki-history-provenance', $html);
        // The author cell is exactly the 1.32 markup (no extra whitespace or element).
        self::assertSame(1, preg_match('#data-label="Author">Editor 3f2a</td>#', $html));
    }

    public function testTheCommentsPanelShowsBadgesEscaped(): void
    {
        $html = $this->createTwig()->render('@NowoWikiBundle/manage/page_view.html.twig', self::pageView(true));

        self::assertSame(1, preg_match('#<span class="nowo-wiki-comment__author">Editor 9b1c</span> <span class="[^"]*nowo-wiki-member-badge nowo-wiki-member-badge--neutral" data-testid="wiki-member-badge">&lt;img src=x onerror=alert\(1\)&gt;</span>#', $html));
        self::assertStringNotContainsString('<img src=x', $html);

        $plain = $this->createTwig()->render('@NowoWikiBundle/manage/page_view.html.twig', self::pageView(false));
        self::assertStringNotContainsString('wiki-member-badge', $plain);
        self::assertSame(1, preg_match('#<span class="nowo-wiki-comment__author">Editor 9b1c</span>\s*<time#', $plain));
    }

    /**
     * @return array<string, mixed>
     */
    private static function history(bool $hooks): array
    {
        $factory   = PageFixtures::factory();
        $space     = PageFixtures::space('engineering', 'Engineering');
        [, $page]  = PageFixtures::pages($space);
        $user      = new TestUser();
        $revisions = [];
        foreach ([4, 3, 2] as $number) {
            $revisions[] = new WikiPageRevision($page, $number, '<p>v' . $number . '</p>', $user);
        }
        $page->setCurrentRevision($revisions[0]);
        $entry    = static fn (string $author, array $extra = []): array => ['author' => $author, 'named' => false, 'avatar' => null, 'label' => null, 'saves' => 1, 'deleted' => $author === ''] + $extra;
        $timeline = $hooks ? [
            4 => $entry('Editor 3f2a', ['badges' => [new WikiMemberBadge('Agent', 'agent', 'Service "account"')], 'channel' => 'mcp', 'actor_label' => self::SCRIPT . ' token']),
            3 => $entry('Editor 9b1c', ['badges' => [], 'channel' => 'web', 'actor_label' => null]),
            // A former person: the timeline gives no label; a badge would not be shown either.
            2 => $entry('', ['badges' => [new WikiMemberBadge('Agent', 'agent')], 'channel' => null, 'actor_label' => 'Import job']),
        ] : [
            4 => $entry('Editor 3f2a'),
            3 => $entry('Editor 9b1c'),
            2 => $entry(''),
        ];

        return $factory->context([
            'page'            => $factory->history($space, $page, $revisions, null, [], Pagination::fromTotal(3, 1, 3, [3], 3, 'nowo_wiki_page_history', [], ['spaceSlug' => 'engineering', 'pageSlug' => 'deploy']), $timeline),
            'routes'          => PageFixtures::routes(),
            'dashboard_route' => null,
        ]);
    }

    /**
     * The server-rendered comments list with a badge on the opaque author of c3 (the WikiCommentService JSON shape).
     *
     * @return array<string, mixed>
     */
    private static function pageView(bool $badges): array
    {
        $context = PageFixtures::pageView(false, null, true, false);
        if ($badges && is_array($context['comments'] ?? null)) {
            $context['comments']['page_threads'][0]['comments'][2]['author']['badges'] = [['label' => '<img src=x onerror=alert(1)>', 'variant' => 'neutral', 'title' => null]];
        }

        return $context;
    }
}
