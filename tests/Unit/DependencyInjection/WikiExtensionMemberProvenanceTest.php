<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection;

use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Nowo\WikiBundle\Event\WikiEvents;
use Nowo\WikiBundle\History\WikiActorLabels;
use Nowo\WikiBundle\History\WikiHistoryTimeline;
use Nowo\WikiBundle\Member\NullWikiMemberBadgeProvider;
use Nowo\WikiBundle\Member\WikiMemberBadgeProviderInterface;
use Nowo\WikiBundle\Member\WikiMemberBadges;
use Nowo\WikiBundle\Provenance\NullWikiWriteProvenanceProvider;
use Nowo\WikiBundle\Provenance\WikiProvenanceAuthorErasedListener;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceErasure;
use Nowo\WikiBundle\Provenance\WikiWriteProvenanceProviderInterface;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiPageProvenanceFinderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Spec 234: both hooks default to null providers by alias; the badge memo is reset on kernel.reset and fed to
 * the history timeline and the comments when they are on.
 */
final class WikiExtensionMemberProvenanceTest extends TestCase
{
    public function testTheHooksDefaultToNullProviders(): void
    {
        $container = $this->load(['history' => ['timeline' => true], 'editor' => ['type' => 'milkdown'], 'comments' => ['enabled' => true, 'rate_limiter' => 'limiter.c']]);

        self::assertSame(NullWikiMemberBadgeProvider::class, (string) $container->getAlias(WikiMemberBadgeProviderInterface::class));
        self::assertSame(NullWikiWriteProvenanceProvider::class, (string) $container->getAlias(WikiWriteProvenanceProviderInterface::class));
        self::assertSame(DoctrineOrmWikiPageRevisionRepository::class, (string) $container->getAlias(WikiPageProvenanceFinderInterface::class));
        self::assertTrue($container->getDefinition(WikiWriteProvenanceErasure::class)->isPublic());
        self::assertSame([['method' => 'reset']], $container->getDefinition(WikiMemberBadges::class)->getTag('kernel.reset'));
        foreach ([WikiHistoryTimeline::class, WikiCommentService::class] as $reader) {
            $badges = $container->getDefinition($reader)->getArgument('$badges');
            self::assertInstanceOf(Reference::class, $badges);
            self::assertSame(WikiMemberBadges::class, (string) $badges);
        }
    }

    /** Audit PQ-M1: actor labels follow history.show_names unless history.show_actor_labels says otherwise. */
    public function testActorLabelsFollowShowNamesByDefault(): void
    {
        self::assertFalse($this->load([])->getDefinition(WikiActorLabels::class)->getArgument('$show'));
        self::assertTrue($this->load(['history' => ['show_names' => true]])->getDefinition(WikiActorLabels::class)->getArgument('$show'));
        self::assertFalse($this->load(['history' => ['show_names' => true, 'show_actor_labels' => false]])->getDefinition(WikiActorLabels::class)->getArgument('$show'));
        $timeline = $this->load(['history' => ['timeline' => true]])->getDefinition(WikiHistoryTimeline::class)->getArgument('$actorLabels');
        self::assertSame(WikiActorLabels::class, (string) $timeline);

        $listener = $this->load([])->getDefinition(WikiProvenanceAuthorErasedListener::class);
        self::assertSame([['event' => WikiEvents::COMMENT_AUTHOR_ERASED]], $listener->getTag('kernel.event_listener'));
    }

    public function testShowActorLabelsIsABooleanOrNull(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('history.show_actor_labels must be true, false or null.');

        $this->load(['history' => ['show_actor_labels' => 'yes']]);
    }

    public function testReadersThatAreOffAreSkipped(): void
    {
        $container = $this->load(['history' => ['timeline' => false], 'comments' => ['enabled' => false]]);

        self::assertFalse($container->hasDefinition(WikiHistoryTimeline::class));
        self::assertFalse($container->hasDefinition(WikiCommentService::class));
        self::assertTrue($container->hasDefinition(WikiMemberBadges::class));
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
