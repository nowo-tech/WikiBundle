<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Nowo\TransactionDoctrine\HostFlushProbe;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiPageRevision;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Service\WikiPageOperationsInterface;
use Nowo\WikiBundle\Service\WikiRequestBoundaryResetter;
use Nowo\WikiBundle\Service\WikiSpaceOperationsInterface;
use Nowo\WikiBundle\Transaction\WikiFlushObserver;
use Nowo\WikiBundle\Transaction\WikiTransactionRunner;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;

use function sprintf;
use function strrpos;
use function substr;
use function trigger_deprecation;

/**
 * 1.49 (REQ-ARCH-001 rule 6 deprecation layer): the migration list of a host that runs the shared entity manager
 * (`database.private_entity_manager: false`, the 1.x default). Registered only in that mode; the private manager never
 * flushes the application's manager, so it needs no probe.
 *
 * Two kinds of `E_USER_DEPRECATED`, each at most once per class (and kind of write) per unit of work:
 *
 * - **Your changes flushed by a wiki write.** Right before every wiki flush of the shared manager
 *   ({@see WikiFlusher::flush()}, {@see WikiTransactionRunner::run()}) the shared
 *   nowo-tech/transaction-doctrine {@see HostFlushProbe} lists the application's entity classes with pending work: "Your
 *   pending changes to App\X were flushed by NowoWikiBundle; 2.0 no longer does." Flush them yourself.
 * - **Wiki rows written by your manager.** A flush the wiki did not run that inserts, updates or deletes wiki rows
 *   (Doctrine `onFlush` on `database.entity_manager`) names the wiki class and, where one exists, the id operation to
 *   use instead ({@see WikiSpaceOperationsInterface}, {@see WikiPageOperationsInterface}): in 2.0 the wiki reads and
 *   writes its rows only through its private manager, whose instances are not yours.
 *
 * Read-only: it never flushes (other than the wiki flush it wraps), clears, closes, resets or detaches anything
 * (REQ-RUNTIME-003), and a failure of its own (a deprecation handler that throws) never fails a write. State (what was already reported, whether a wiki flush is running) is emptied at every request,
 * message and command boundary ({@see WikiRequestBoundaryResetter}) and at `kernel.reset`, so a FrankenPHP worker with
 * kernel reset off reports each unit of work afresh. The shared probe is reset by transaction-doctrine's own boundary
 * listener; without that bundle this service keeps a probe of its own and resets it with itself.
 *
 * @internal
 */
final class WikiHostFlushProbe implements ResetInterface
{
    /** The bundle name the deprecations show. */
    public const BUNDLE = 'NowoWikiBundle';

    /** The wiki's entity namespace: its classes are never "your pending changes". */
    public const ENTITY_NAMESPACE = 'Nowo\\WikiBundle\\Entity';

    /** The package of the notices. */
    private const PACKAGE = 'nowo-tech/wiki-bundle';

    /** The minor that introduced the notices. */
    private const SINCE = '1.49';

    /** Write kinds, as the notices name them. */
    private const INSERT = 'inserted';

    private const UPDATE = 'updated';

    private const DELETE = 'deleted';

    /**
     * The id operation to use instead of a write of the wiki class through the application's manager, where one exists.
     *
     * @var array<class-string, array<string, string>>
     */
    private const OPERATIONS = [
        WikiSpace::class => [
            self::INSERT => 'create spaces with ' . WikiSpaceOperationsInterface::class . '::createSpace() and keep their id',
        ],
        WikiPage::class => [
            self::INSERT => 'create pages with ' . WikiPageOperationsInterface::class . '::createPage() (or createPageAt() / createPageNextTo()) and keep their id',
            self::UPDATE => 'change pages with ' . WikiPageOperationsInterface::class . ' (saveRevision(), restoreRevision(), archivePage(), unarchivePage())',
        ],
        WikiPageRevision::class => [
            self::INSERT => 'add revisions with ' . WikiPageOperationsInterface::class . '::saveRevision() or restoreRevision()',
        ],
    ];

    private readonly HostFlushProbe $probe;

    /** Whether {@see self::$probe} is this service's own (no transaction-doctrine bundle): then reset with it. */
    private readonly bool $ownProbe;

    /** Wiki flushes running right now (a flush inside a wiki flush is the wiki's too). */
    private int $wikiFlushes = 0;

    /** @var array<string, true> "class|kind" pairs of wiki-row writes already reported in this unit of work */
    private array $reported = [];

    /**
     * @param HostFlushProbe|null $probe the shared probe (`nowo_transaction_doctrine.host_flush_probe`); null = a probe
     *                                   of this service's own, reset with it
     */
    public function __construct(?HostFlushProbe $probe = null)
    {
        $this->ownProbe = !$probe instanceof HostFlushProbe;
        $this->probe    = $probe ?? new HostFlushProbe();
    }

    /**
     * Runs a wiki flush of the shared manager: reports the application's pending changes it writes first, and marks
     * the flush as the wiki's for {@see self::onFlush()}. The caller does the flush (and its closed-manager check,
     * {@see WikiFlusher::flush()}).
     *
     * @param EntityManagerInterface $entityManager the shared manager (`database.entity_manager`)
     * @param callable(): void $flush the flush of that manager
     */
    public function around(EntityManagerInterface $entityManager, callable $flush): void
    {
        // Since transaction-doctrine 1.9.1 the shared probe never throws (a deprecation handler that throws, an unexpected
        // unit of work: logged, nothing reported), so a notice never stops a write: the flush below runs exactly as it
        // did before 1.49.
        $this->probe->beforeHostFlush($entityManager, self::BUNDLE, [self::ENTITY_NAMESPACE]);
        // @igor-ignore - per unit of work, back to its value when the flush ends; emptied by reset() at every boundary
        ++$this->wikiFlushes;
        try {
            $flush();
        } finally {
            // @igor-ignore - per unit of work, see above
            --$this->wikiFlushes;
        }
    }

    /**
     * Doctrine `onFlush` of `database.entity_manager`: a flush the wiki did not run that writes wiki rows.
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        if ($this->wikiFlushes > 0) {
            return;
        }
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();
        foreach ([
            self::INSERT => $unitOfWork->getScheduledEntityInsertions(),
            self::UPDATE => $unitOfWork->getScheduledEntityUpdates(),
            self::DELETE => $unitOfWork->getScheduledEntityDeletions(),
        ] as $kind => $entities) {
            foreach ($entities as $entity) {
                $class = $this->realClass($entity::class);
                if (WikiFlushObserver::isWikiEntity($class)) {
                    $this->report($class, $kind);
                }
            }
        }
    }

    /** A new unit of work (request, message, command) starts: nothing reported yet. */
    public function reset(): void
    {
        // @igor-ignore - the boundary reset itself
        $this->reported = [];
        // @igor-ignore - the boundary reset itself
        $this->wikiFlushes = 0;
        if ($this->ownProbe) {
            $this->probe->reset();
        }
    }

    /**
     * One notice per class and kind of write per unit of work.
     */
    private function report(string $class, string $kind): void
    {
        $key = $class . '|' . $kind;
        if (isset($this->reported[$key])) {
            return;
        }
        // @igor-ignore - per unit of work, emptied by reset() at every boundary
        $this->reported[$key] = true;
        $instead              = self::OPERATIONS[$class][$kind] ?? 'no id operation exists for this write yet: write it through the wiki\'s services with rows loaded through the wiki, and pass ids, not your instances';
        try {
            trigger_deprecation(self::PACKAGE, self::SINCE, sprintf(
                'Your application %s %s rows with its own entity manager, outside a wiki write. In 2.0 the wiki reads and writes its rows only through its private entity manager, whose instances are not yours: %s (then set "nowo_wiki.database.private_entity_manager: true").',
                $kind,
                $class,
                $instead,
            ));
        } catch (Throwable) {
            // A deprecation handler that throws never fails the application's flush (this runs inside its onFlush).
        }
    }

    /** The entity class behind a Doctrine proxy class. */
    private function realClass(string $class): string
    {
        $marker = strrpos($class, '\\__CG__\\');

        return $marker === false ? $class : substr($class, $marker + 8);
    }
}
