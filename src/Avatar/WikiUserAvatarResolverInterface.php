<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Avatar;

use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The host's picture URLs of wiki users (1.16, avatar contract v1.1 §10.2): history authors and comment authors.
 *
 * WikiBundle asks only for people whose real name it shows (`history.show_names`, a name that passes
 * {@see WikiCollabService::isSafeName()}): a person shown as an opaque "Editor xxxx" is
 * never passed here, so a stable picture URL can never link their edits across pages. The bundle asks once per
 * response ({@see self::urlsFor()}), after its access filters.
 *
 * A URL must be stable for the life of the page and access-checked when the browser fetches it (no bearer secret in
 * it): `https://` without userinfo, or a same-origin path starting with one `/`. Anything else is dropped by the
 * UiKit avatar URL policy (\Nowo\UiKitBundle\Avatar\AvatarUrlPolicy) and the initials are shown. The default
 * ({@see NullWikiUserAvatarResolver}) returns no URL; alias this interface to the host's service.
 */
interface WikiUserAvatarResolverInterface
{
    /**
     * The picture of one user, shown at `$size` CSS pixels (WikiBundle asks for {@see WikiAvatars::SIZE}).
     */
    public function urlFor(UserInterface $user, int $size): ?string;

    /**
     * The pictures of several users, keyed by {@see WikiAvatarKey::of()}; a missing key means no picture.
     *
     * @param list<UserInterface> $users distinct users
     *
     * @return array<string, string|null>
     */
    public function urlsFor(array $users, int $size): array;
}
