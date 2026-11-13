<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Port;

use Nowo\WikiBundle\Link\WikiLinkedObject;
use Nowo\WikiBundle\Link\WikiObjectLinkResolverInterface;
use Nowo\WikiBundle\Link\WikiObjectLinkSourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;

/**
 * Asks a framework-free {@see WikiObjectLinkSourceInterface} (1.33.0). Only objects of the asked type, keyed by an
 * asked id (byte for byte) and describing that id, are kept.
 *
 * @internal
 */
final readonly class WikiObjectLinkSourceGate implements WikiObjectLinkResolverInterface
{
    public function __construct(private WikiObjectLinkSourceInterface $source)
    {
    }

    /**
     * The resolver to ask for a tagged service: itself when it implements the deprecated interface, a gate when it
     * implements only the twin, null otherwise.
     */
    public static function of(object $service): ?WikiObjectLinkResolverInterface
    {
        return match (true) {
            $service instanceof WikiObjectLinkResolverInterface => $service,
            $service instanceof WikiObjectLinkSourceInterface   => new self($service),
            default                                             => null,
        };
    }

    public function supports(string $objectType): bool
    {
        return $this->source->supports($objectType);
    }

    public function resolveVisible(UserInterface $user, string $objectType, array $objectIds): array
    {
        $visible = [];
        foreach ($this->source->resolveVisible(WikiUserRefs::fromUser($user), $objectType, $objectIds) as $id => $object) {
            $id = (string) $id;
            if ($object instanceof WikiLinkedObject && $object->id === $id && $object->type === $objectType && in_array($id, $objectIds, true)) {
                $visible[$id] = $object;
            }
        }

        return $visible;
    }
}
