<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Security\WikiTokenGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class WikiTokenGuardTest extends TestCase
{
    public function testTokenIsTrustedOutsideHttpRequests(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::never())->method('getFirewallConfig');

        self::assertTrue((new WikiTokenGuard(new RequestStack(), $security))->isTokenTrusted());
    }

    public function testTokenIsTrustedOnlyForRequestsBehindSecuredFirewall(): void
    {
        $secured   = Request::create('/wiki');
        $public    = Request::create('/public');
        $unmatched = Request::create('/assets');

        $security = $this->createMock(Security::class);
        $security->method('getFirewallConfig')->willReturnCallback(static fn (Request $request): ?FirewallConfig => match ($request) {
            $secured => new FirewallConfig('main', 'user_checker'),
            $public  => new FirewallConfig('public', 'user_checker', null, false),
            default  => null,
        });

        $requestStack = new RequestStack();
        $guard        = new WikiTokenGuard($requestStack, $security);

        // Same worker, three consecutive requests, no reset in between.
        $requestStack->push($secured);
        self::assertTrue($guard->isTokenTrusted());
        $requestStack->pop();

        $requestStack->push($public);
        self::assertFalse($guard->isTokenTrusted());
        $requestStack->pop();

        $requestStack->push($unmatched);
        self::assertFalse($guard->isTokenTrusted());
    }

    public function testWithoutSecurityServiceFallsBackToFirewallContextAttribute(): void
    {
        $requestStack = new RequestStack();
        $guard        = new WikiTokenGuard($requestStack);

        $withFirewall = Request::create('/wiki');
        $withFirewall->attributes->set('_firewall_context', 'security.firewall.map.context.main');
        $requestStack->push($withFirewall);
        self::assertTrue($guard->isTokenTrusted());
        $requestStack->pop();

        $requestStack->push(Request::create('/public'));
        self::assertFalse($guard->isTokenTrusted());
    }
}
