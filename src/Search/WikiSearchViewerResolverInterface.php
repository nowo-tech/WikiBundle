<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Search;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Port (1.44, `search_kit_provider.viewer_resolver`): the user a search made for a user identifier runs as. SearchKit
 * hands its providers only the viewer's identifier (`SearchQuery::$userIdentifier`), never the session user, so a search
 * run for someone else (`SearchEngineInterface::searchFor()`, a CLI, an API) is decided for that person.
 *
 * Return the user whose identifier is exactly `$userIdentifier`, or null (unknown, disabled, any doubt): the provider
 * then finds nothing. The provider re-checks `getUserIdentifier() === $userIdentifier` and ignores a mismatch. Never
 * answer with the user of the current security token.
 */
interface WikiSearchViewerResolverInterface
{
    public function viewer(string $userIdentifier): ?UserInterface;
}
