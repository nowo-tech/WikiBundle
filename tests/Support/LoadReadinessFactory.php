<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\WikiBundle\Attachment\LocalWikiAttachmentStorage;
use Nowo\WikiBundle\Attachment\NullWikiAttachmentScanner;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimits;
use Nowo\WikiBundle\Attachment\WikiAttachmentLimitsProviderInterface;
use Nowo\WikiBundle\Attachment\WikiAttachmentService;
use Nowo\WikiBundle\Comment\WikiCommentService;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\History\WikiAuthorLabel;
use Nowo\WikiBundle\Link\WikiPageLinkService;
use Nowo\WikiBundle\Markdown\WikiMarkdownRenderer;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiAttachmentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiCommentRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageLinkRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRevisionRepository;
use Nowo\WikiBundle\Repository\WikiAttachmentRepositoryInterface;
use Nowo\WikiBundle\Repository\WikiPageRevisionRepositoryInterface;
use Nowo\WikiBundle\Security\WikiHtmlSanitizer;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiPageService;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Nowo\WikiBundle\Util\WikiSlugger;
use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function array_flip;
use function array_intersect_key;
use function array_map;
use function class_exists;

/**
 * 1.37 (Beacon spec 239): the wiki services of the load-readiness tests, wired as the bundle wires them (the transaction
 * runner included). Arguments are passed by name and only when the constructor has them, so the same tests also run
 * against an older release (which has no runner) to show the races it had.
 */
final class LoadReadinessFactory
{
    /** Whether this checkout has the transaction runner (1.37+). */
    public static function hasRunner(): bool
    {
        return class_exists(WikiTransactionRunner::class);
    }

    /** The runner of a manager, or null before 1.37. */
    public static function runner(EntityManagerInterface $em): ?object
    {
        return self::hasRunner() ? new WikiTransactionRunner($em, backoffBaseMilliseconds: 0) : null;
    }

    /** A second manager on the same connection: the other request of a race. */
    public static function peer(EntityManager $em): EntityManager
    {
        return new EntityManager($em->getConnection(), $em->getConfiguration(), $em->getEventManager());
    }

    public static function pages(EntityManagerInterface $em, EventDispatcherInterface $dispatcher = new EventDispatcher(), ?WikiPageRevisionRepositoryInterface $revisions = null): WikiPageService
    {
        return self::make(WikiPageService::class, [
            'pageRepository'     => new DoctrineOrmWikiPageRepository($em),
            'revisionRepository' => $revisions ?? new DoctrineOrmWikiPageRevisionRepository($em),
            'slugger'            => new WikiSlugger(),
            'htmlSanitizer'      => new WikiHtmlSanitizer(),
            'eventDispatcher'    => $dispatcher,
            'runner'             => self::runner($em),
        ]);
    }

    public static function links(EntityManagerInterface $em, EventDispatcherInterface $dispatcher = new EventDispatcher()): WikiPageLinkService
    {
        return self::make(WikiPageLinkService::class, [
            'linkRepository'  => new DoctrineOrmWikiPageLinkRepository($em),
            'resolvers'       => [],
            'eventDispatcher' => $dispatcher,
            'runner'          => self::runner($em),
        ]);
    }

    public static function comments(EntityManagerInterface $em, WikiSpace $space, EventDispatcherInterface $dispatcher = new EventDispatcher()): WikiCommentService
    {
        return self::make(WikiCommentService::class, [
            'comments'   => new DoctrineOrmWikiCommentRepository($em),
            'guard'      => new WikiSpaceGuard(new FixedSpaceLocator($space), new CallbackSpaceAccessChecker(static fn (): bool => true)),
            'authors'    => new WikiAuthorLabel('REDACTED-TEST', true),
            'renderer'   => new WikiMarkdownRenderer(),
            'sanitizer'  => new WikiHtmlSanitizer(),
            'dispatcher' => $dispatcher,
            'runner'     => self::runner($em),
        ]);
    }

    /**
     * @param int $maxSpaceBytes the space quota
     */
    public static function attachments(EntityManagerInterface $em, string $directory, int $maxSpaceBytes, ?WikiAttachmentRepositoryInterface $repository = null): WikiAttachmentService
    {
        $limits = new class($maxSpaceBytes) implements WikiAttachmentLimitsProviderInterface {
            public function __construct(private readonly int $maxSpaceBytes)
            {
            }

            public function limits(WikiSpace $space): WikiAttachmentLimits
            {
                return new WikiAttachmentLimits(1_000_000, $this->maxSpaceBytes, ['txt' => ['text/plain']]);
            }
        };

        return self::make(WikiAttachmentService::class, [
            'attachmentRepository' => $repository ?? new DoctrineOrmWikiAttachmentRepository($em),
            'storage'              => new LocalWikiAttachmentStorage($directory),
            'scanner'              => new NullWikiAttachmentScanner(),
            'limitsProvider'       => $limits,
            'eventDispatcher'      => new EventDispatcher(),
            'runner'               => self::runner($em),
        ]);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<string, mixed> $named constructor arguments by name; unknown names are left out
     *
     * @return T
     */
    public static function make(string $class, array $named): object
    {
        $reflection = new ReflectionClass($class);
        $names      = array_map(static fn ($parameter): string => $parameter->getName(), $reflection->getConstructor()?->getParameters() ?? []);

        return $reflection->newInstanceArgs(array_intersect_key($named, array_flip($names)));
    }
}
