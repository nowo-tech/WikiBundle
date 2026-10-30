<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\WikiBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * P2: the Web UI refuses to compile without form CSRF protection (fail closed).
 */
final class CsrfProtectionPassTest extends TestCase
{
    public function testPassesWithFormCsrfAndATokenManager(): void
    {
        $this->expectNotToPerformAssertions();
        (new CsrfProtectionPass())->process($this->container());
    }

    public function testSkipsWithoutTheWikiOrWithTheUiDisabled(): void
    {
        $this->expectNotToPerformAssertions();
        (new CsrfProtectionPass())->process(new ContainerBuilder());

        $disabled = new ContainerBuilder();
        $disabled->setParameter('nowo_wiki.web_ui', ['enabled' => false]);
        (new CsrfProtectionPass())->process($disabled);

        $malformed = new ContainerBuilder();
        $malformed->setParameter('nowo_wiki.web_ui', 'on');
        (new CsrfProtectionPass())->process($malformed);
    }

    /**
     * @return iterable<string, array{0: bool, 1: bool, 2: bool|null}>
     */
    public static function unsafeContainers(): iterable
    {
        yield 'no token manager' => [false, true, null];
        yield 'no form CSRF extension' => [true, false, null];
        yield 'form CSRF disabled' => [true, true, false];
    }

    #[DataProvider('unsafeContainers')]
    public function testFailsClosedWithoutCsrf(bool $tokenManager, bool $formExtension, ?bool $formCsrfEnabled): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires CSRF protection');

        (new CsrfProtectionPass())->process($this->container($tokenManager, $formExtension, $formCsrfEnabled));
    }

    private function container(bool $tokenManager = true, bool $formExtension = true, ?bool $formCsrfEnabled = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('nowo_wiki.web_ui', ['enabled' => true]);
        if ($tokenManager) {
            $container->setDefinition('security.csrf.token_manager', new Definition('stdClass'));
        }
        if ($formExtension) {
            $container->setDefinition('form.type_extension.csrf', new Definition('stdClass'));
        }
        if ($formCsrfEnabled !== null) {
            $container->setParameter('form.type_extension.csrf.enabled', $formCsrfEnabled);
        }

        return $container;
    }
}
