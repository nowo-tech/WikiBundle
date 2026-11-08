<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\Compiler\WikiApiSafetyPass;
use Nowo\WikiBundle\Repository\DoctrineOrmWikiPageRepository;
use Nowo\WikiBundle\Repository\WikiPageRepositoryInterface;
use Nowo\WikiBundle\Security\AllowAllWikiAccessChecker;
use Nowo\WikiBundle\Security\ConfigurableWikiAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Tests\Support\ConcurrentlyEditedPageRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * 1.24 (audit IF-L5): the API refuses to build over an allow-all role gate or a page repository without the lock.
 */
final class WikiApiSafetyPassTest extends TestCase
{
    public function testNothingIsCheckedWithTheApiOff(): void
    {
        $container = $this->container(AllowAllWikiAccessChecker::class, ConcurrentlyEditedPageRepository::class);
        $container->getParameterBag()->remove('nowo_wiki.api.prefix');

        (new WikiApiSafetyPass())->process($container);
        $this->addToAssertionCount(1);
    }

    public function testASafeHostBuilds(): void
    {
        (new WikiApiSafetyPass())->process($this->container(ConfigurableWikiAccessChecker::class, DoctrineOrmWikiPageRepository::class));
        $empty = new ContainerBuilder();
        $empty->setParameter('nowo_wiki.api.prefix', '/api');
        (new WikiApiSafetyPass())->process($empty);
        $this->addToAssertionCount(1);
    }

    public function testAnAllowAllRoleGateBehindAnAliasIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be used with ' . AllowAllWikiAccessChecker::class);
        (new WikiApiSafetyPass())->process($this->container('%app.checker_class%', DoctrineOrmWikiPageRepository::class));
    }

    public function testAPageRepositoryWithoutTheLockIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs a page repository that implements');
        (new WikiApiSafetyPass())->process($this->container(ConfigurableWikiAccessChecker::class, ConcurrentlyEditedPageRepository::class));
    }

    private function container(string $checker, string $pages): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_wiki.api.prefix', '/api/wiki/v1');
        $container->setParameter('app.checker_class', AllowAllWikiAccessChecker::class);
        $container->setDefinition('app.checker', new Definition($checker));
        $container->setAlias(WikiAccessCheckerInterface::class, 'app.checker');
        $container->setDefinition('app.pages', new Definition($pages));
        $container->setAlias(WikiPageRepositoryInterface::class, 'app.pages');

        return $container;
    }
}
