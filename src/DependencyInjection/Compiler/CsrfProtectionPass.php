<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use LogicException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_array;

/**
 * Fails closed when the wiki Web UI is enabled without form CSRF protection (REQ-SEC-005, cross-bundle pattern P2).
 *
 * Every mutation of the wiki UI (page create / edit, archive, unarchive, restore, import, AI question, attachment
 * upload) relies on Symfony's form CSRF extension or the CSRF token manager. A host that disables
 * `framework.form.csrf_protection` (or has no CSRF token manager) would silently turn those checks off, so the
 * container refuses to compile instead.
 */
final class CsrfProtectionPass implements CompilerPassInterface
{
    /**
     * Throws when the Web UI (`web_ui.enabled`) is on and form CSRF protection or the CSRF token manager is unavailable.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_wiki.web_ui')) {
            return;
        }

        $webUi = $container->getParameter('nowo_wiki.web_ui');
        if (!is_array($webUi) || !($webUi['enabled'] ?? true)) {
            return;
        }

        $formCsrfDisabled = $container->hasParameter('form.type_extension.csrf.enabled')
            && $container->getParameter('form.type_extension.csrf.enabled') === false;

        if (
            !$container->has('security.csrf.token_manager')
            || !$container->hasDefinition('form.type_extension.csrf')
            || $formCsrfDisabled
        ) {
            throw new LogicException('NowoWikiBundle: the wiki Web UI (web_ui.enabled: true) requires CSRF protection. Enable framework.csrf_protection and framework.form.csrf_protection (composer require symfony/security-csrf), or set nowo_wiki.web_ui.enabled: false (the wiki routes are then not registered).');
        }
    }
}
