<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api\Security;

use Nowo\WikiBundle\Api\Hook\WikiApiDirectoryInterface;
use Nowo\WikiBundle\Api\Value\WikiApiWiki;
use Nowo\WikiBundle\Api\WikiApiPrincipal;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Markdown\WikiRenderReach;

/**
 * 1.24 (spec 218): a token's reach for one render: a space counts only when the directory answers its wiki for the token
 * and *where* reaches it. Decided once per space per render.
 */
final class WikiApiRenderReach implements WikiRenderReach
{
    /** @var array<string, bool> space id => reached */
    private array $decided = [];

    public function __construct(
        private readonly WikiApiGuard $guard,
        private readonly WikiApiDirectoryInterface $directory,
        private readonly WikiApiPrincipal $principal,
    ) {
    }

    public function reachesSpace(WikiSpace $space): bool
    {
        if (!isset($this->decided[$space->getId()])) {
            $wiki = $this->directory->ofSpace($this->principal, $space);
            // @igor-ignore - One reach per render, made by WikiApiGuard::reach(); never a shared service.
            $this->decided[$space->getId()] = $wiki instanceof WikiApiWiki && $wiki->canOpen && $wiki->space?->getId() === $space->getId() && $this->guard->sees($this->principal, $wiki);
        }

        return $this->decided[$space->getId()];
    }
}
