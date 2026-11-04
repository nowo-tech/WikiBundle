<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Integration;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\AllowlistIconResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullPageResolver;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\NullUserResolver;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Nowo\WikiBundle\Search\WikiPlainText;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Service\WikiSearchService;
use Nowo\WikiBundle\Tests\Stub\TestUserEntity;
use Nowo\WikiBundle\Tests\Support\SqliteWikiEntityManager;
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * 1.16 (addendum "search must ignore directive markers"): Milkdown 1.7 callout icons (`:::callout{icon="bulb"
 * type="info"}`) are not search hits; the stored content_text is matched instead of the Markdown source.
 */
final class WikiSearchPlainTextTest extends TestCase
{
    public function testDirectiveAttributesAreNotSearchHits(): void
    {
        $em       = SqliteWikiEntityManager::create();
        $space    = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $author   = new TestUserEntity('ana');
        $renderer = new MilkdownWikiMarkdownRenderer([], new NullPageResolver(), new NullUserResolver(), new AllowlistIconResolver('/icons/tabler.svg', ['bulb', 'rocket']));
        $sanitize = new WikiHtmlSanitizer(WikiHtmlSanitizer::DEFAULT_EMBED_HOSTS, WikiExtension::MILKDOWN_SANITIZER_ELEMENTS, WikiExtension::MILKDOWN_SANITIZER_ATTRIBUTES);
        foreach ([$space, $author] as $entity) {
            $em->persist($entity);
        }
        $pages = [
            'callout' => ":::callout{icon=\"bulb\" type=\"info\"}\nUse a torch in the server room.\n:::",
            'icon'    => 'Ship it :tabler-rocket: today.',
            'plain'   => 'The bulb in the hallway is broken.',
        ];
        foreach ($pages as $slug => $markdown) {
            $page     = new WikiPage($space, $slug, 'Page ' . $slug);
            $revision = new WikiPageRevision($page, 1, $sanitize->sanitize($renderer->render($markdown)), $author, $markdown);
            $page->setCurrentRevision($revision);
            $em->persist($page);
            $em->persist($revision);
        }
        $em->flush();
        $em->clear();

        $search = new WikiSearchService($em);
        $space  = $em->find(WikiSpace::class, $space->getId());
        self::assertInstanceOf(WikiSpace::class, $space);
        $titles = static fn (array $hits): array => array_map(static fn (array $hit): string => $hit['page']->getTitle(), $hits);

        // "bulb" is only a callout attribute on the callout page: not a hit there, a hit on the page that says it.
        self::assertSame(['Page plain'], $titles($search->search($space, 'bulb')));
        self::assertSame([], $titles($search->search($space, 'type="info"')));
        self::assertSame([], $titles($search->search($space, 'tabler-rocket')));
        self::assertSame(['Page callout'], $titles($search->search($space, 'torch')));
        self::assertSame(['Page icon'], $titles($search->search($space, 'Ship it')));
        $hit = $search->search($space, 'torch')[0];
        self::assertSame('Use a torch in the server room.', $hit['excerpt']);

        // The Markdown reducer agrees.
        self::assertStringNotContainsString('bulb', WikiPlainText::fromMarkdown($pages['callout']));
    }

    public function testRevisionsWithoutTextAreStillFoundTheOldWay(): void
    {
        $em     = SqliteWikiEntityManager::create();
        $space  = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $author = new TestUserEntity('ana');
        $page   = new WikiPage($space, 'old', 'Old');
        $page->setCurrentRevision($revision = new WikiPageRevision($page, 1, '<p>Legacy <em>body</em></p>', $author));
        foreach ([$space, $author, $page, $revision] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->getConnection()->executeStatement('UPDATE wiki_page_revisions SET content_text = NULL');
        $em->clear();

        $space = $em->find(WikiSpace::class, $space->getId());
        self::assertInstanceOf(WikiSpace::class, $space);
        $hits = (new WikiSearchService($em))->search($space, 'Legacy');
        self::assertCount(1, $hits);
        self::assertSame('Legacy body', $hits[0]['excerpt']);
    }
}
