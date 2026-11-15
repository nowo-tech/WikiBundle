<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

use function sprintf;

/**
 * Resolves the configured wiki entity manager on every call.
 *
 * 1.36.0: with `database.private_entity_manager: true` it is the wiki's private manager ({@see WikiEntityManager}),
 * so a wiki write never flushes, clears or closes the application's manager (REQ-ARCH-001 rule 6).
 *
 * 1.37 (Beacon spec 239, audit QD-M1): a shared manager closed by a failed flush is no longer reset here, in the middle
 * of a request (that detached every entity the application had loaded, behind its back). It is returned as it is: reads
 * still work, a wiki write answers a transient failure ({@see WikiFlusher}), and the bundle's transaction runner
 * replaces it at the next request, message or command boundary, so a long-running worker recovers without depending on
 * `services_resetter`.
 */
final readonly class WikiEntityManagerProvider
{
    /**
     * Stores the registry and the name of the entity manager to resolve.
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     * @param string $managerName name of the wiki entity manager
     * @param WikiEntityManager|null $private 1.36.0 (`database.private_entity_manager: true`): the wiki's private
     *                                        manager on the connection and mapping of `$managerName`
     */
    public function __construct(
        private ManagerRegistry $registry,
        private string $managerName = 'default',
        private ?WikiEntityManager $private = null,
    ) {
    }

    /**
     * Returns the wiki entity manager (1.37: a closed shared manager as it is, see the class description).
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
