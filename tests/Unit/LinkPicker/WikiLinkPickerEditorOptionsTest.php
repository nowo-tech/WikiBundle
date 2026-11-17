<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\LinkPicker;

use Nowo\MilkdownEditorBundle\ProfileRegistry;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\LinkPicker\WikiLinkPickerEditorOptions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * 1.40 (W2): the Milkdown options of the page editor — `stable_heading_ids`, `link_picker` and the picker URLs, added to
 * the field's or the profile's features, never replacing what a host already set.
 */
final class WikiLinkPickerEditorOptionsTest extends TestCase
{
    private const ROUTES = [
        'link_candidates' => ['path' => '/c', 'name' => 'nowo_wiki_link_candidates'],
        'link_sections'   => ['path' => '/s', 'name' => 'nowo_wiki_link_sections'],
    ];

    private WikiSpace $space;

    protected function setUp(): void
    {
        $this->space = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
    }

    public function testTheProfileFeaturesGetBothFeaturesAndTheUrls(): void
    {
        $options = $this->options(true)->apply([], $this->space);

        self::assertSame(['toolbar', 'table', 'stable_heading_ids', 'link_picker'], $options['features']);
        self::assertSame(['[[' => '/tools/wiki/spaces/eng/link-candidates'], $options['suggestions']);
        self::assertSame('/tools/wiki/spaces/eng/link-candidates/{id}/sections', $options['link_sections']);
    }

    public function testAHostKeepsItsOwnValues(): void
    {
        $options = $this->options(true)->apply(['features' => ['toolbar', 'link_picker'], 'suggestions' => ['[[' => '/mine', '@' => '/users'], 'link_sections' => '/mine/{id}'], $this->space);

        self::assertSame(['toolbar', 'link_picker', 'stable_heading_ids'], $options['features']);
        self::assertSame(['[[' => '/mine', '@' => '/users'], $options['suggestions']);
        self::assertSame('/mine/{id}', $options['link_sections']);
    }

    public function testStableIdsAloneAndNothingAtAll(): void
    {
        $stable = $this->options(false)->apply(['profile' => 'wiki'], $this->space);
        self::assertSame(['toolbar', 'table', 'stable_heading_ids'], $stable['features']);
        self::assertArrayNotHasKey('suggestions', $stable);

        self::assertSame(['x' => 1], $this->options(false, false)->apply(['x' => 1], $this->space));
        self::assertSame(['stable_heading_ids'], (new WikiLinkPickerEditorOptions($this->urls(), self::ROUTES))->apply([], $this->space)['features']);
    }

    public function testWithoutRoutesOrWithARoutingErrorThePickerStaysOff(): void
    {
        $noRoutes = (new WikiLinkPickerEditorOptions($this->urls(), [], true, false))->apply(['x' => 1], $this->space);
        self::assertSame(['x' => 1], $noRoutes);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static function (string $name): string {
            if ($name === 'nowo_wiki_link_sections') {
                throw new RouteNotFoundException();
            }

            return '/c';
        });
        $partial = (new WikiLinkPickerEditorOptions($urls, self::ROUTES, true, false))->apply([], $this->space);
        self::assertSame(['link_picker'], $partial['features']);
        self::assertSame(['[[' => '/c'], $partial['suggestions']);
        self::assertArrayNotHasKey('link_sections', $partial);
    }

    private function options(bool $picker, bool $stable = true): WikiLinkPickerEditorOptions
    {
        return new WikiLinkPickerEditorOptions(
            $this->urls(),
            self::ROUTES,
            $picker,
            $stable,
            new ProfileRegistry(['wiki' => ['features' => ['toolbar', 'table'], 'theme' => 'auto', 'min_height' => '1rem', 'icon_sprite_url' => null, 'icons' => [], 'upload_max_size' => 1, 'upload_accept' => []]], 'wiki'),
        );
    }

    private function urls(): UrlGeneratorInterface
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $name, array $params): string => $name === 'nowo_wiki_link_candidates'
            ? '/tools/wiki/spaces/' . $params['spaceSlug'] . '/link-candidates'
            : '/tools/wiki/spaces/' . $params['spaceSlug'] . '/link-candidates/' . $params['pageId'] . '/sections');

        return $urls;
    }
}
