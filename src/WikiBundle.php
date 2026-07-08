<?php

declare(strict_types=1);

namespace Nowo\WikiBundle;

use Nowo\WikiBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\WikiBundle\DependencyInjection\WikiExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Versionable team wiki for Symfony applications (Outline/Notion-style pages with Tiptap).
 */
final class WikiBundle extends Bundle
{
    public const TRANSLATION_DOMAIN = 'NowoWikiBundle';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());
    }

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new WikiExtension();
        }

        return $this->extension;
    }
}
