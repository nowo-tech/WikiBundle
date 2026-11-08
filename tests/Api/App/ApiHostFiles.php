<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Api\App;

use Nowo\MilkdownEditorBundle\Markdown\Resolver\ContextualHostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ResolvedFile;
use Nowo\WikiBundle\Doctrine\WikiEntityManagerProvider;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;

use function explode;

/**
 * The host's file resolver in the API tests, as Beacon's should be: a file belongs to a wiki, and a render whose frame
 * does not reach that wiki (the token's *where*) does not see it.
 */
final readonly class ApiHostFiles implements ContextualHostFileResolverInterface
{
    public function __construct(
        private WikiEntityManagerProvider $entityManager,
    ) {
    }

    public function resolveFiles(array $references): array
    {
        return $this->resolveFilesInContext($references, null);
    }

    public function resolveFilesInContext(array $references, ?object $context): array
    {
        $out = [];
        foreach ($references as $reference) {
            $uuid  = explode(':', $reference, 2)[1] ?? '';
            $slug  = ApiTestFiles::$wikis[$uuid] ?? null;
            $space = $slug !== null ? $this->entityManager->get()->getRepository(WikiSpace::class)->findOneBy(['slug' => $slug]) : null;
            if (!$space instanceof WikiSpace || ($context instanceof WikiRenderFrame && !$context->reaches($space))) {
                continue;
            }
            $out[$reference] = new ResolvedFile('/files/' . $uuid . '/original', '/files/' . $uuid . '/preview');
        }

        return $out;
    }
}
