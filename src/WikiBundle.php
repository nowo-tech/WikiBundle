<?php

declare(strict_types=1);

namespace Nowo\WikiBundle;

use Nowo\WikiBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\WikiBundle\DependencyInjection\Compiler\MilkdownBridgePass;
use Nowo\WikiBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiApiSafetyPass;
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

    /**
     * Registers the bundle compiler passes (Twig template paths, fail-closed CSRF check of the Web UI).
     *
     * @param ContainerBuilder $container container being compiled
     */
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new TwigPathsPass());
        $container->addCompilerPass(new CsrfProtectionPass());
        $container->addCompilerPass(new MilkdownBridgePass());
        $container->addCompilerPass(new WikiApiSafetyPass());
    }

    /**
     * Returns the bundle DI extension, lazily creating and caching a WikiExtension instance.
     *
     * @return ExtensionInterface the `nowo_wiki` configuration extension
     */
    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            // @igor-ignore - Boot-time Symfony Bundle extension cache (not request state).
            $this->extension = new WikiExtension();
        }

        return $this->extension;
    }
}
