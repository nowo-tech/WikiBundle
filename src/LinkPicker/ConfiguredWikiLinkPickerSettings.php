<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\LinkPicker;

/**
 * 1.40: the link picker settings as configured (`nowo_wiki.editor.milkdown.link_picker.cross_wiki`). Immutable.
 */
final readonly class ConfiguredWikiLinkPickerSettings implements WikiLinkPickerSettingsInterface
{
    /**
     * @param bool $crossWiki `link_picker.cross_wiki`
     */
    public function __construct(
        private bool $crossWiki = true,
    ) {
    }

    public function crossWiki(): bool
    {
        return $this->crossWiki;
    }
}
