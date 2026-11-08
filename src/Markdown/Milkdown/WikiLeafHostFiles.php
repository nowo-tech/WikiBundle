<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown\Milkdown;

use League\CommonMark\Event\DocumentParsedEvent;
use Nowo\MilkdownEditorBundle\Markdown\Directive\DirectiveRendererInterface;
use Nowo\MilkdownEditorBundle\Markdown\FileCard;
use Nowo\MilkdownEditorBundle\Markdown\HostSchemes;
use Nowo\MilkdownEditorBundle\Markdown\HostValue;
use Nowo\MilkdownEditorBundle\Markdown\Node\LeafDirective;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\ContextualHostFileResolverInterface;
use Nowo\MilkdownEditorBundle\Markdown\Resolver\RenderContextProviderInterface;
use Psr\Log\LoggerInterface;
use Throwable;

use function array_flip;
use function array_intersect_key;
use function array_keys;
use function array_slice;

/**
 * 1.25 (audit IJ-L2): the URL attributes of leaf directives ({@see WikiLeafDirectiveRenderer::URL_ATTRIBUTES}) that are
 * host files go through the host's file resolver, with the render's frame, before {@see WikiLeafDirectiveRenderer} may
 * turn them into links. Milkdown resolves only the `href` of `::file`; this covers `url` and `link` on `::file` and every
 * URL attribute of other directives (`::download{href=…}`). A directive with a host value the resolver refuses (or that
 * it cannot check) is marked `nowo_wiki_host_hidden` and renders the neutral placeholder; a `::file` whose `href` is a
 * host value links only that `href` (`nowo_wiki_href_only`), never a `url` or `link` next to it.
 *
 * Directives a host renderer supports are left to it. Viewer environment only, and only with host schemes.
 */
final readonly class WikiLeafHostFiles
{
    /** Data key of a directive that names a host file the viewer may not see. */
    public const HIDDEN = 'nowo_wiki_host_hidden';

    /** Data key of a `::file` whose link may only be its (resolved) `href`. */
    public const HREF_ONLY = 'nowo_wiki_href_only';

    /**
     * @param list<DirectiveRendererInterface> $directiveRenderers host leaf-directive renderers (they check their own)
     */
    public function __construct(
        private HostSchemes $hostSchemes,
        private ?ContextualHostFileResolverInterface $hostFiles,
        private ?RenderContextProviderInterface $renderContext,
        private array $directiveRenderers,
        private int $maxReferences,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onDocumentParsed(DocumentParsedEvent $event): void
    {
        /** @var list<array{LeafDirective, list<?string>}> $found */
        $found      = [];
        $references = [];
        foreach ($event->getDocument()->iterator() as $node) {
            if (!$node instanceof LeafDirective || $this->hostRendered($node->name)) {
                continue;
            }
            $file = $node->name === FileCard::NAME;
            if ($file && $this->hostSchemes->classify($node->attributes['href'] ?? '') instanceof HostValue) {
                $node->data->set(self::HREF_ONLY, true);
            }
            $values = [];
            foreach (WikiLeafDirectiveRenderer::URL_ATTRIBUTES as $key) {
                // Milkdown already resolved the `href` of `::file` (nowo_file_hidden).
                if (($file && $key === 'href') || !isset($node->attributes[$key])) {
                    continue;
                }
                $value = $this->hostSchemes->classify($node->attributes[$key]);
                if ($value instanceof HostValue) {
                    $values[] = $value->reference;
                    if ($value->reference !== null) {
                        $references[$value->reference] = true;
                    }
                }
            }
            if ($values !== []) {
                $found[] = [$node, $values];
            }
        }
        if ($found === []) {
            return;
        }

        $ids      = array_slice(array_keys($references), 0, $this->maxReferences);
        $resolved = $ids === [] ? [] : array_intersect_key($this->resolve($ids), array_flip($ids));
        foreach ($found as [$node, $values]) {
            foreach ($values as $reference) {
                if ($reference === null || !isset($resolved[$reference])) {
                    $node->data->set(self::HIDDEN, true);
                    break;
                }
            }
        }
    }

    /**
     * @param list<string> $references
     *
     * @return array<string, mixed>
     */
    private function resolve(array $references): array
    {
        if (!$this->hostFiles instanceof ContextualHostFileResolverInterface) {
            return [];
        }
        try {
            return $this->hostFiles->resolveFilesInContext($references, $this->renderContext?->renderContext());
        } catch (Throwable $e) {
            $this->logger?->warning('Wiki renderer: the host file resolver failed; directive files render as not visible.', ['exception' => $e::class]);

            return [];
        }
    }

    private function hostRendered(string $name): bool
    {
        foreach ($this->directiveRenderers as $renderer) {
            if ($renderer->supports($name)) {
                return true;
            }
        }

        return false;
    }
}
