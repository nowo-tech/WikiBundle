<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

use function sprintf;

/**
 * Resolves the wiki's entity manager: its private manager ({@see WikiEntityManager}, REQ-ARCH-001 rule 6), on the
 * connection and mapping of `database.entity_manager`, so a wiki write never flushes, clears or closes the application's
 * manager. 2.0: the bundle's service always has the private manager (the only mode).
 *
 * Built by hand without a private manager (a test, an application that wires the wiki itself), the registry's
 * `$managerName` manager is resolved on every call and treated as the wiki's own manager: never hand it the manager your
 * application writes through.
 */
final readonly class WikiEntityManagerProvider
{
    /**
     * @param ManagerRegistry $registry Doctrine manager registry
     * @param string $managerName name of the source entity manager (`database.entity_manager`)
     * @param WikiEntityManager|null $private the wiki's private manager on the connection and mapping of `$managerName`
     *                                        (always set by the bundle)
     * @param object|null $hostFlushProbe @deprecated since 2.0, ignored (the 1.49 host-flush probe of the removed
     *                                    shared manager); removed in 3.0
     */
    public function __construct(
        private ManagerRegistry $registry,
        private string $managerName = 'default',
        private ?WikiEntityManager $private = null,
        ?object $hostFlushProbe = null,
    ) {
        unset($hostFlushProbe);
    }

    /**
     * @deprecated since 2.0, always null (the host-flush probe of the removed shared manager); removed in 3.0
     */
    public function hostFlushProbe(): null
    {
        return null;
    }

    /**
     * Returns the wiki entity manager: the private one (a manager built by hand: the registry's `$managerName`).
     *
     * @throws LogicException when the configured manager is not an ORM entity manager
     *
     * @return EntityManagerInterface the wiki entity manager
     */
    public function get(): EntityManagerInterface
    {
        if ($this->private instanceof WikiEntityManager) {
            return $this->private;
        }

        $manager = $this->registry->getManager($this->managerName);
        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('Doctrine manager "%s" is not an ORM entity manager.', $this->managerName));
        }

        return $manager;
    }
}
