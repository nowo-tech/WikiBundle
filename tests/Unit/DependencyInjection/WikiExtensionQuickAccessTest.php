<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use LogicException;
use Nowo\WikiBundle\Backlink\WikiBacklinks;
use Nowo\WikiBundle\Backlink\WikiPageReferenceIndex;
use Nowo\WikiBundle\Backlink\WikiPageReferenceListener;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaimErasureListener;
use Nowo\WikiBundle\Collaboration\WikiCollabSeedClaims;
use Nowo\WikiBundle\Command\WikiBacklinksRebuildCommand;
use Nowo\WikiBundle\Command\WikiCollabSeedClaimsClearCommand;
use Nowo\WikiBundle\Command\WikiQuickAccessEraseUserCommand;
use Nowo\WikiBundle\Command\WikiRecentsPurgeCommand;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Doctrine\WikiMetadataListener;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiRenderedRevisionDiff;
use Nowo\WikiBundle\LinkPicker\WikiLinkTenantResolverInterface;
use Nowo\WikiBundle\LinkPicker\WikiTenantPathsLinkTenantResolver;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccess;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessErasureListener;
use Nowo\WikiBundle\QuickAccess\WikiQuickAccessStore;
use Nowo\WikiBundle\QuickAccess\WikiReadableSpaces;
use Nowo\WikiBundle\Transaction\WikiConflictAnswer;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 1.45: `favourites`, `recents`, `backlinks` and `history.rendered_diff` — nothing registered (no route, no table in
 * the schema) while off; the services, commands, listener and route when on.
 */
final class WikiExtensionQuickAccessTest extends TestCase
{
    public function testEverythingIsOffByDefault(): void
    {
        $container = $this->load([]);

        /** @var array<string, mixed> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertArrayNotHasKey('page_favourite', $routes);
        foreach ([WikiQuickAccess::class, WikiReadableSpaces::class, WikiBacklinks::class, WikiPageReferenceIndex::class, WikiPageReferenceListener::class, WikiRenderedRevisionDiff::class, WikiBacklinksRebuildCommand::class] as $id) {
            // An excluded class may stay as an abstract `container.excluded` definition (never a service).
            self::assertTrue(!$container->hasDefinition($id) || $container->getDefinition($id)->hasTag('container.excluded'), $id);
        }
        self::assertTrue($container->hasDefinition(WikiConflictAnswer::class));
        // Audit VC-3: the erasure (command and listener), the store and the purge are always there.
        self::assertTrue($container->getDefinition(WikiQuickAccessEraseUserCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(WikiRecentsPurgeCommand::class)->hasTag('console.command'));
        self::assertSame(90, $container->getDefinition(WikiRecentsPurgeCommand::class)->getArgument('$retentionDays'));
        self::assertSame([['event' => WikiEvents::COMMENT_AUTHOR_ERASED, 'method' => 'onAuthorErased']], $container->getDefinition(WikiQuickAccessErasureListener::class)->getTag('kernel.event_listener'));
        self::assertTrue($container->hasDefinition(WikiQuickAccessStore::class));
        // 2.0.1 (Beacon 207 T030): the seed claims' erasure, export and clean-up, always (co-editing is off here).
        self::assertSame([['event' => WikiEvents::COMMENT_AUTHOR_ERASED, 'method' => 'onAuthorErased']], $container->getDefinition(WikiCollabSeedClaimErasureListener::class)->getTag('kernel.event_listener'));
        self::assertSame('%kernel.secret%', $container->getDefinition(WikiCollabSeedClaims::class)->getArgument('$secret'));
        self::assertTrue($container->getDefinition(WikiCollabSeedClaimsClearCommand::class)->hasTag('console.command'));
        self::assertArrayNotHasKey('favourite_remove', (array) $container->getParameter('nowo_wiki.routes'));
        $listener = $container->getDefinition(WikiMetadataListener::class);
        self::assertFalse($listener->getArgument('$favouritesEnabled'));
        self::assertFalse($listener->getArgument('$recentsEnabled'));
        self::assertFalse($listener->getArgument('$backlinksEnabled'));
        self::assertSame('wiki', $listener->getArgument('$quickAccessTablePrefix'));
    }

    public function testFavouritesAndRecentsWireTheirServicesAndRoute(): void
    {
        $container = $this->load(['favourites' => ['enabled' => true, 'max' => 5, 'shown' => 3], 'recents' => ['enabled' => true, 'keep' => 7]]);

        /** @var array<string, array{path: string, name: string}> $routes */
        $routes = $container->getParameter('nowo_wiki.routes');
        self::assertSame('nowo_wiki_page_favourite', $routes['page_favourite']['name']);
        self::assertSame('/tools/wiki/spaces/{spaceSlug}/pages/{pageSlug}/favourite', $routes['page_favourite']['path']);
        $quick = $container->getDefinition(WikiQuickAccess::class);
        self::assertSame(['enabled' => true, 'max' => 5, 'shown' => 3], $quick->getArgument('$favourites'));
        self::assertSame(['enabled' => true, 'keep' => 7, 'shown' => 8, 'retention_days' => 90], $quick->getArgument('$recents'));
        self::assertSame('/tools/wiki/favourites/{favouriteId}/remove', $routes['favourite_remove']['path']);
        self::assertSame(WikiTenantPathsLinkTenantResolver::class, (string) $container->getAlias(WikiLinkTenantResolverInterface::class));
        self::assertTrue($container->getDefinition(WikiMetadataListener::class)->getArgument('$favouritesEnabled'));
        self::assertFalse($container->hasDefinition(WikiBacklinks::class));

        // Recents alone: the service without the route.
        $recents = $this->load(['recents' => ['enabled' => true]]);
        self::assertTrue($recents->hasDefinition(WikiQuickAccess::class));
        self::assertArrayNotHasKey('page_favourite', (array) $recents->getParameter('nowo_wiki.routes'));
    }

    public function testBacklinksWireTheIndexListenerFinderAndCommand(): void
    {
        $container = $this->load(['table_prefix' => 'kb_', 'backlinks' => ['enabled' => true, 'limit' => 5, 'candidates' => 50]]);

        self::assertSame([['event' => WikiEvents::PAGE_SAVED, 'method' => 'onPageSaved']], $container->getDefinition(WikiPageReferenceListener::class)->getTag('kernel.event_listener'));
        $finder = $container->getDefinition(WikiBacklinks::class);
        self::assertSame(5, $finder->getArgument('$limit'));
        self::assertSame(50, $finder->getArgument('$candidates'));
        self::assertTrue($container->hasDefinition(WikiReadableSpaces::class));
        self::assertTrue($container->getDefinition(WikiBacklinksRebuildCommand::class)->hasTag('console.command'));
        $listener = $container->getDefinition(WikiMetadataListener::class);
        self::assertTrue($listener->getArgument('$backlinksEnabled'));
        self::assertSame('kb', $listener->getArgument('$quickAccessTablePrefix'));

        // With favourites too, one readable-spaces service is shared; the link picker's tenant port is kept.
        $both = $this->load(['favourites' => ['enabled' => true], 'backlinks' => ['enabled' => true], 'editor' => ['type' => 'milkdown', 'milkdown' => ['link_picker' => ['enabled' => true, 'rate_limiter' => 'limiter.x']]]]);
        self::assertTrue($both->hasDefinition(WikiReadableSpaces::class));
        self::assertSame(WikiTenantPathsLinkTenantResolver::class, (string) $both->getAlias(WikiLinkTenantResolverInterface::class));
    }

    /** Audit VC2-1: with comments, the comment service's export gets the store. */
    public function testTheCommentServiceExportGetsTheStore(): void
    {
        $container = $this->load(['editor' => ['type' => 'milkdown'], 'comments' => ['enabled' => true, 'rate_limiter' => 'limiter.c']]);

        self::assertSame(WikiQuickAccessStore::class, (string) $container->getDefinition(WikiCommentService::class)->getArgument('$quickAccess'));
        self::assertSame(WikiCollabSeedClaims::class, (string) $container->getDefinition(WikiCommentService::class)->getArgument('$seedClaims'));
    }

    public function testTheRenderedDiffNeedsMilkdown(): void
    {
        $container = $this->load(['editor' => ['type' => 'milkdown'], 'history' => ['rendered_diff' => true], 'sanitizer' => ['extra_allowed_elements' => ['abbr' => ['title']]]]);
        $diff      = $container->getDefinition(WikiRenderedRevisionDiff::class);
        self::assertArrayHasKey('abbr', $diff->getArgument('$extraAllowedElements'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('history.rendered_diff needs nowo_wiki.editor.type: milkdown');
        $this->load(['history' => ['rendered_diff' => true]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SecurityBundle' => SecurityBundle::class]);
        (new WikiExtension())->load([['user_class' => 'App\\Entity\\User'] + $config], $container);

        return $container;
    }
}
