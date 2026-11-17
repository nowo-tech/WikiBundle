<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\LinkPicker;

/**
 * 1.40: the runtime settings of the link picker (`nowo_wiki.editor.milkdown.link_picker`), read on every request
 * (FrankenPHP worker mode: nothing is cached). The default, {@see ConfiguredWikiLinkPickerSettings}, returns the
 * configured values; alias this interface to your own service to read them from elsewhere (for instance a
 * nowo-tech/runtime-setting-contract backend, from `.env` or the database).
 */
interface WikiLinkPickerSettingsInterface
{
    /**
     * Whether the picker also offers pages of the other wikis of the same tenant (`cross_wiki`). False: the current
     * wiki only.
     */
    public function crossWiki(): bool;
}
