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
 * A manager closed by a failed flush in an earlier request is reset through the registry, so a
 * long-running worker recovers without depending on `services_resetter`.
 */
final readonly class WikiEntityManagerProvider
{
    /**
     * Stores the registry and the name of the entity manager to resolve.
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     * @param string $managerName name of the wiki entity manager
     */
    public function __construct(
        private ManagerRegistry $registry,
        private string $managerName = 'default',
    ) {
    }

    /**
     * Returns the wiki entity manager, resetting it first when it has been closed.
     *
     * @throws LogicException when the configured manager is not an ORM entity manager
     *
     * @return EntityManagerInterface open wiki entity manager
     */
    public function get(): EntityManagerInterface
    {
        $manager = $this->registry->getManager($this->managerName);
        if ($manager instanceof EntityManagerInterface && !$manager->isOpen()) {
            $manager = $this->registry->resetManager($this->managerName);
        }

        if (!$manager instanceof EntityManagerInterface) {
            throw new LogicException(sprintf('Doctrine manager "%s" is not an ORM entity manager.', $this->managerName));
        }

        return $manager;
    }
}
