<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use LogicException;
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
 */
final class MilkdownBridgePass implements CompilerPassInterface
{
    /** Parameter set by NowoMilkdownEditorBundle with its processed `markdown` configuration. */
    public const OPTIONS_PARAMETER = 'nowo_milkdown_editor.markdown';

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

        $container->getDefinition(MilkdownWikiMarkdownRenderer::class)->setArgument('$options', $options);
    }
}
