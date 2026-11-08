<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\Repository\WikiPageLockInterface;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function is_a;
use function is_string;

/**
 * 1.24 (spec 218, audit IF-L5): with `api.enabled`, refuses to build when the role gate lets everyone through
 * ({@see AllowAllWikiAccessChecker}, however it is wired) or when the page repository cannot lock a page (base-revision
 * saves would fail on every write).
 */
final class WikiApiSafetyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('nowo_wiki.api.prefix')) {
            return;
        }
        $checker = $this->classOf($container, WikiAccessCheckerInterface::class);
        if ($checker !== null && is_a($checker, AllowAllWikiAccessChecker::class, true)) {
            throw new LogicException('nowo_wiki.api.enabled cannot be used with ' . AllowAllWikiAccessChecker::class . ' as the access checker: every token would get past the role gate.');
        }
        $pages = $this->classOf($container, WikiPageRepositoryInterface::class);
        if ($pages !== null && !is_a($pages, WikiPageLockInterface::class, true)) {
            throw new LogicException('nowo_wiki.api.enabled needs a page repository that implements ' . WikiPageLockInterface::class . ' (base_revision is checked under a row lock); ' . $pages . ' does not.');
        }
    }

    /** The class behind a service id or alias, following aliases; null when it cannot be told. */
    private function classOf(ContainerBuilder $container, string $id): ?string
    {
        if (!$container->has($id)) {
            return null;
        }
        $class = $container->findDefinition($id)->getClass();
        $class = is_string($class) ? $container->getParameterBag()->resolveValue($class) : null;

        return is_string($class) ? $class : null;
    }
}
