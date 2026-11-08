<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\Configuration;
use Nowo\WikiBundle\Markdown\Milkdown\MilkdownWikiMarkdownRenderer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_array;

/**
 * 1.13 (`nowo_wiki.editor.type: milkdown`): hands the `nowo_milkdown_editor.markdown` options (safety settings and
 * limits: `html_input`, `allow_remote_images`, `max_nesting_level`, `max_input_length`, `max_inline_markers`,
 * `max_references`, `mark_colors`, `math`) to {@see MilkdownWikiMarkdownRenderer}, so wiki pages are rendered under
 * exactly the limits the Milkdown renderer and form type enforce. Fails closed when NowoMilkdownEditorBundle is not
 * registered.
 *
 * 1.25: applies `nowo_wiki.editor.milkdown.host_files`. Unless it is `on` (or `auto` with Milkdown's `host_url_patterns`
 * set), the renderer gets no host schemes and no file resolver, so host references render as in 1.24.
 */
final class MilkdownBridgePass implements CompilerPassInterface
{
    /** Parameter set by NowoMilkdownEditorBundle with its processed `markdown` configuration. */
    public const OPTIONS_PARAMETER = 'nowo_milkdown_editor.markdown';

    /** 1.25: tag of the renderer carrying `editor.milkdown.host_files` (`mode`). */
    public const HOST_FILES_TAG = 'nowo_wiki.milkdown_host_files';

    /**
     * @throws LogicException when the bridge is on and NowoMilkdownEditorBundle is not registered
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MilkdownWikiMarkdownRenderer::class)) {
            return;
        }

        $options = $container->hasParameter(self::OPTIONS_PARAMETER) ? $container->getParameter(self::OPTIONS_PARAMETER) : null;
        if (!is_array($options)) {
            throw new LogicException('nowo_wiki.editor.type "milkdown" requires NowoMilkdownEditorBundle to be registered in the kernel (config/bundles.php).');
        }

        // CV-I8: a parameter reference, so %env()% limits are resolved at runtime (the renderer accepts numeric strings).
        $renderer = $container->getDefinition(MilkdownWikiMarkdownRenderer::class);
        $renderer->setArgument('$options', '%' . self::OPTIONS_PARAMETER . '%');

        $mode     = $renderer->getTag(self::HOST_FILES_TAG)[0]['mode'] ?? Configuration::HOST_FILES_AUTO;
        $patterns = $options['host_url_patterns'] ?? [];
        if ($mode === Configuration::HOST_FILES_OFF || ($mode !== Configuration::HOST_FILES_ON && (!is_array($patterns) || $patterns === []))) {
            $renderer->setArgument('$hostSchemes', null)->setArgument('$hostFiles', null);
        }
        $renderer->clearTag(self::HOST_FILES_TAG);
    }
}
