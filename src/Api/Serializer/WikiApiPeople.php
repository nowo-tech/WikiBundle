<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Serializer;

use Nowo\WikiBundle\Api\Hook\WikiApiUsersInterface;
use Nowo\WikiBundle\Api\Security\WikiApiGuard;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\History\WikiAuthorLabel;

use function array_filter;
use function array_values;

/**
 * 1.24 (spec 218, owner Q-O5): people in answers — the name the history policy shows ({@see WikiAuthorLabel}: an
 * opaque per-page label unless `history.show_names`), and an id only for the token's own user. Never a login or an
 * e-mail.
 */
final readonly class WikiApiPeople
{
    public function __construct(
        private WikiAuthorLabel $labels,
        private WikiApiUsersInterface $users,
        private WikiApiGuard $guard,
    ) {
    }

    /**
     * @param list<object|null> $people
     *
     * @return list<array{id: string|null, name: string}|null> in the order of $people; null for nobody
     */
    public function presentAll(WikiApiPrincipal $principal, array $people, WikiPage $page): array
    {
        $known     = array_values(array_filter($people, static fn (?object $person): bool => $person !== null));
        $presented = $known === [] ? [] : $this->labels->presentAll($known, $page);
        $answer    = [];
        $index     = 0;
        foreach ($people as $person) {
            if ($person === null) {
                $answer[] = null;
                continue;
            }
            $answer[] = [
                'id'   => $this->guard->isUser($principal, $person) ? $this->users->apiId($person) : null,
                'name' => $presented[$index++]['name'],
            ];
        }

        return $answer;
    }

    /**
     * @return array{id: string|null, name: string}|null
     */
    public function present(WikiApiPrincipal $principal, ?object $person, WikiPage $page): ?array
    {
        return $this->presentAll($principal, [$person], $page)[0];
    }
}
