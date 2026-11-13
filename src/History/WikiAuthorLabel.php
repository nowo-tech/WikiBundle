<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use LogicException;
use Nowo\WikiBundle\Avatar\WikiAvatars;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\User\WikiUsers;
use Nowo\WikiBundle\ValueObject\WikiStoredUserRef;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_map;
use function hash_hmac;
use function is_string;
use function method_exists;
use function preg_replace;
use function substr;
use function trim;

/**
 * The name shown for the author of a revision in the history timeline (1.15) and of a comment (1.16): the same
 * opaque, per-page "Editor xxxx" as co-editing (a subkey of `kernel.secret`, so a label cannot be matched against a
 * directory nor linked across pages), or, with `history.show_names`, the user's display name when it says nothing
 * about the login (the {@see WikiCollabService::isSafeName()} rule).
 *
 * 1.16 (avatar contract §10.2 / §11): {@see self::present()} and {@see self::presentAll()} add the author's picture
 * URL, and only beside a real name. Where the name is hidden (`show_names` off, or a name that fails `isSafeName()`)
 * the author is never passed to the avatar resolver: no URL is resolved nor emitted.
 */
final readonly class WikiAuthorLabel
{
    /**
     * @param WikiAvatars|null $avatars the response's avatar memo (null: no pictures)
     * @param WikiUsers|null $users 1.33 (`database.user_reference: id`): the host users behind stored ids
     */
    public function __construct(
        private string $secret,
        private bool $showNames = false,
        private ?WikiAvatars $avatars = null,
        private ?WikiUsers $users = null,
    ) {
    }

    public function label(object $author, WikiPage $page): string
    {
        $author = $this->users?->resolve($author) ?? $author;

        return $this->realName($author) ?? $this->opaque($author, $page);
    }

    /**
     * The label and the picture URL of one author (null when the name is hidden or there is no picture).
     *
     * @return array{name: string, named: bool, avatarUrl: string|null}
     */
    public function present(object $author, WikiPage $page): array
    {
        return $this->presentAll([$author], $page)[0];
    }

    /**
     * The labels and picture URLs of several authors, with one resolver call for all the named ones.
     *
     * @param list<object> $authors
     *
     * @return list<array{name: string, named: bool, avatarUrl: string|null}> in the order of $authors
     */
    public function presentAll(array $authors, WikiPage $page): array
    {
        $names = [];
        $named = [];
        // 1.33: a stored id reference is presented as the host user the directory finds for it (else by its id).
        $authors = array_map(fn (object $author): object => $this->users?->resolve($author) ?? $author, $authors);
        foreach ($authors as $index => $author) {
            $names[$index] = $this->realName($author);
            if ($names[$index] !== null && $author instanceof UserInterface) {
                $named[] = $author;
            }
        }
        // The privacy gate: only users shown under their real name reach the resolver.
        if ($named !== []) {
            $this->avatars?->prime($named);
        }

        $presented = [];
        foreach ($authors as $index => $author) {
            $name        = $names[$index];
            $presented[] = [
                'name'      => $name ?? $this->opaque($author, $page),
                'named'     => $name !== null,
                'avatarUrl' => $name !== null && $author instanceof UserInterface ? $this->avatars?->urlOf($author) : null,
            ];
        }

        return $presented;
    }

    /** The safe display name, or null when the name is hidden. */
    private function realName(object $author): ?string
    {
        $identifier = $author instanceof UserInterface ? $author->getUserIdentifier() : '';
        if (!$this->showNames || $identifier === '') {
            return null;
        }
        foreach (['getDisplayName', 'getName'] as $method) {
            $name = method_exists($author, $method) ? $author->{$method}() : null;
            if (is_string($name) && WikiCollabService::isSafeName($name, $identifier)) {
                return trim((string) preg_replace('/\s+/u', ' ', $name));
            }
        }

        return null;
    }

    private function opaque(object $author, WikiPage $page): string
    {
        if ($this->secret === '') {
            throw new LogicException('nowo_wiki.history needs a non-empty kernel.secret to derive the author labels.');
        }
        $identifier = match (true) {
            $author instanceof UserInterface     => $author->getUserIdentifier(),
            $author instanceof WikiStoredUserRef => "\0id:" . $author->id, // 1.33: a stored id the directory did not resolve
            default                              => '',
        };
        $hash = hash_hmac('sha256', $page->getId() . '|' . $identifier, hash_hmac('sha256', 'nowo_wiki_collab_label', $this->secret));

        return 'Editor ' . substr($hash, 6, 4);
    }
}
