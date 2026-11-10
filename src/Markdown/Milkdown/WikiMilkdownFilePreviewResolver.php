<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use Nowo\MilkdownEditorBundle\Markdown\HostSchemes;
use Nowo\MilkdownEditorBundle\Markdown\HostValue;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\FilePreviewResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\FileReference;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\RenderContextProviderInterface;
use Nowo\WikiBundle\Markdown\WikiRenderContext;
use Nowo\WikiBundle\Markdown\WikiRenderFrame;
use Nowo\WikiBundle\Markdown\WikiRenderReach;

use function array_filter;
use function array_values;
use function is_string;

/**
 * 1.28 (Symfony Beacon spec 212 block B): the host's Milkdown `FilePreviewResolverInterface` as the wiki's per-viewer
 * render calls it, inside the render's {@see WikiRenderFrame} (the resolver may read it from {@see WikiRenderContext}).
 *
 * Milkdown asks only about `view="preview"` cards the viewer may see (a refused host file reads *Content not visible*
 * first). Under a reach (the REST API's *where*) only cards whose `href` is a host reference are passed on: those went
 * through the contextual host file resolution of that reach ({@see WikiMilkdownHostFileResolver}); a plain URL card
 * could name a file of any space, so it gets no preview there. The answer is kept only for the cards asked about.
 */
final readonly class WikiMilkdownFilePreviewResolver implements FilePreviewResolverInterface
{
    public function __construct(
        private FilePreviewResolverInterface $inner,
        private HostSchemes $hostSchemes = new HostSchemes(),
        private ?RenderContextProviderInterface $renderContext = null,
    ) {
    }

    public function resolvePreviews(array $files): array
    {
        $frame = $this->renderContext?->renderContext();
        if ($frame instanceof WikiRenderFrame && $frame->reach instanceof WikiRenderReach) {
            $files = array_values(array_filter($files, fn (FileReference $file): bool => $this->hostSchemes->classify($file->href) instanceof HostValue));
        }
        if ($files === []) {
            return [];
        }

        $out    = [];
        $answer = $this->inner->resolvePreviews($files);
        foreach ($files as $file) {
            $url = $answer[$file->href] ?? null;
            if (is_string($url) && $url !== '') {
                $out[$file->href] = $url;
            }
        }

        return $out;
    }
}
