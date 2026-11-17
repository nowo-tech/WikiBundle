<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\LinkPicker;

use Nowo\MilkdownEditorBundle\ProfileRegistry;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Event\WikiEvents;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function str_replace;

/**
 * 1.40: the Milkdown options of the page editor for section anchors and the link picker (`editor.type: milkdown` only,
 * owner decision O11; Milkdown >= 1.18):
 *
 * - the `stable_heading_ids` feature (`editor.milkdown.stable_heading_ids`): heading ids frozen while editing, written
 *   as `{#id}` when they differ from the heading's slug;
 * - with `editor.milkdown.link_picker.enabled`, the `link_picker` feature, `suggestions['[[']` = the wiki's
 *   `link_candidates` URL and `link_sections` = its `link_sections` URL template (`{id}`). A host that already set
 *   `suggestions['[[']` or `link_sections` (for instance through {@see WikiEvents::EDITOR_OPTIONS})
 *   keeps its value.
 *
 * The features are added to the field's own `features` option, or to those of its Milkdown profile. Stateless.
 */
final readonly class WikiLinkPickerEditorOptions
{
    /** A page id that satisfies the route requirement, replaced by `{id}` in the sections URL template. */
    private const ID_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

    /**
     * @param UrlGeneratorInterface $urlGenerator the (tenant-aware) router
     * @param array<string, array{path: string, name: string}> $routes the nowo_wiki routes (`link_candidates`, `link_sections`)
     * @param bool $linkPicker `editor.milkdown.link_picker.enabled`
     * @param bool $stableHeadingIds `editor.milkdown.stable_heading_ids`
     * @param ProfileRegistry|null $profiles Milkdown profiles (the features of the field's profile)
     */
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private array $routes = [],
        private bool $linkPicker = false,
        private bool $stableHeadingIds = true,
        private ?ProfileRegistry $profiles = null,
    ) {
    }

    /**
     * Adds the features and URLs to the editor options of a page of the space.
     *
     * @param array<string, mixed> $options the editor options so far
     * @param WikiSpace $space the page's space
     *
     * @return array<string, mixed>
     */
    public function apply(array $options, WikiSpace $space): array
    {
        $candidates = $this->linkPicker ? $this->url('link_candidates', ['spaceSlug' => $space->getSlug()]) : null;
        $sections   = $candidates !== null ? $this->url('link_sections', ['spaceSlug' => $space->getSlug(), 'pageId' => self::ID_PLACEHOLDER]) : null;
        $features   = [];
        if ($this->stableHeadingIds) {
            $features[] = 'stable_heading_ids';
        }
        if ($candidates !== null) {
            $features[] = 'link_picker';
        }
        if ($features === []) {
            return $options;
        }

        $current = is_array($options['features'] ?? null) ? array_values($options['features']) : $this->profileFeatures($options['profile'] ?? null);
        foreach ($features as $feature) {
            if (!in_array($feature, $current, true)) {
                $current[] = $feature;
            }
        }
        $options['features'] = $current;

        if ($candidates !== null) {
            $suggestions            = is_array($options['suggestions'] ?? null) ? $options['suggestions'] : [];
            $options['suggestions'] = $suggestions + ['[[' => $candidates];
            if ($sections !== null) {
                $options['link_sections'] ??= str_replace(self::ID_PLACEHOLDER, '{id}', $sections);
            }
        }

        return $options;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function url(string $key, array $parameters): ?string
    {
        $route = $this->routes[$key]['name'] ?? null;
        if ($route === null) {
            return null;
        }

        try {
            return $this->urlGenerator->generate($route, $parameters);
        } catch (RoutingException) {
            return null;
        }
    }

    /**
     * @return list<mixed>
     */
    private function profileFeatures(mixed $profile): array
    {
        if (!$this->profiles instanceof ProfileRegistry) {
            return [];
        }

        return array_values($this->profiles->get(is_string($profile) ? $profile : null)['features']);
    }
}
