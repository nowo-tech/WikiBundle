<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\History;

use LogicException;
use Nowo\WikiBundle\Collaboration\WikiCollabService;
use Nowo\WikiBundle\Entity\WikiPage;
use Symfony\Component\Security\Core\User\UserInterface;

use function hash_hmac;
use function is_string;
use function method_exists;
use function preg_replace;
use function substr;
use function trim;

/**
 * The name shown for the author of a revision in the history timeline (1.15): the same opaque, per-page "Editor xxxx"
 * as co-editing (a subkey of `kernel.secret`, so a label cannot be matched against a directory nor linked across
 * pages), or, with `history.show_names`, the user's display name when it says nothing about the login (the
 * {@see WikiCollabService::isSafeName()} rule).
 */
final readonly class WikiAuthorLabel
{
    public function __construct(
        private string $secret,
        private bool $showNames = false,
    ) {
    }

    public function label(object $author, WikiPage $page): string
    {
        $identifier = $author instanceof UserInterface ? $author->getUserIdentifier() : '';
        if ($this->showNames && $identifier !== '') {
            foreach (['getDisplayName', 'getName'] as $method) {
                $name = method_exists($author, $method) ? $author->{$method}() : null;
                if (is_string($name) && WikiCollabService::isSafeName($name, $identifier)) {
                    return trim((string) preg_replace('/\s+/u', ' ', $name));
                }
            }
        }
        if ($this->secret === '') {
            throw new LogicException('nowo_wiki.history needs a non-empty kernel.secret to derive the author labels.');
        }
        $hash = hash_hmac('sha256', $page->getId() . '|' . $identifier, hash_hmac('sha256', 'nowo_wiki_collab_label', $this->secret));

        return 'Editor ' . substr($hash, 6, 4);
    }
}
