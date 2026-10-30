<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Markdown;

use League\CommonMark\Extension\ExtensionInterface;

/**
 * A league/commonmark extension added to the default {@see WikiMarkdownRenderer} (1.11).
 *
 * Services implementing it are tagged `nowo_wiki.markdown_extension` by autoconfiguration (or tag any
 * {@see ExtensionInterface} service by hand). The bundle registers none: an editor bundle or the host ships the dialect
 * (page links `[[wiki:<uuid>]]`, mentions, icons, directives…) as extensions of this kind.
 */
interface WikiMarkdownExtensionInterface extends ExtensionInterface
{
}
