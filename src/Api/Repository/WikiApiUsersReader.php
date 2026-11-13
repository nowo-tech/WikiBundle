<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Repository;

use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Repository\WikiPageMetadataFinderInterface;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;

use function array_fill_keys;
use function array_keys;
use function count;
use function is_array;
use function is_object;
use function is_scalar;
use function reset;

/**
 * 1.24 (spec 218 FR-019, audit IF-I6): the authors of a list of pages, loaded in one query from the ids
 * {@see WikiPageMetadataFinderInterface} returns, so a page list stays within a fixed number of
 * queries.
 */
final readonly class WikiApiUsersReader
{
    /**
     * @param class-string $userClass `nowo_wiki.user_class`
     */
    public function __construct(
        private WikiEntityManagerProvider $entityManager,
        private string $userClass,
        private ?WikiUsers $users = null,
    ) {
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, object> id => user; unknown ids are left out
     */
    public function byIds(array $ids): array
    {
        $ids = array_keys(array_fill_keys($ids, true));
        if ($ids === []) {
            return [];
        }
        if ($this->users?->idMode() === true) {
            return $this->byStoredIds($ids, $this->users);
        }
        $manager  = $this->entityManager->get();
        $metadata = $manager->getClassMetadata($this->userClass);
        $field    = $metadata->getSingleIdentifierFieldName();
        $users    = $manager->createQueryBuilder()
            ->select('u')
            ->from($this->userClass, 'u')
            ->where('u.' . $field . ' IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach (is_array($users) ? $users : [] as $user) {
            $values = $metadata->getIdentifierValues($user);
            $id     = count($values) === 1 ? reset($values) : null;
            if (is_object($user) && is_scalar($id)) {
                $byId[(string) $id] = $user;
            }
        }

        return $byId;
    }

    /**
     * 1.32 (`database.user_reference: id`): the users behind stored ids, through the host's directory (each answer
     * re-checked byte for byte); an id the directory does not resolve stays a {@see WikiStoredUserRef}.
     *
     * @param list<int|string> $ids distinct ids (numeric ones come back as array keys)
     *
     * @return array<string, object>
     */
    private function byStoredIds(array $ids, WikiUsers $users): array
    {
        $byId = [];
        foreach ($ids as $key) {
            $id = (string) $key;
            if (WikiStoredUserRef::isValid($id)) {
                $byId[$id] = $users->find($id) ?? new WikiStoredUserRef($id);
            }
        }

        return $byId;
    }
}
