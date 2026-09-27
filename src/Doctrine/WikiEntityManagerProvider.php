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
    public function __construct(
        private ManagerRegistry $registry,
        private string $managerName = 'default',
    ) {
    }

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
