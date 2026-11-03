<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\WikiPageVisibility;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Tests\Stub\CallbackSpaceAccessChecker;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use Nowo\WikiBundle\Tests\Support\FixedSpaceLocator;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 1.13.2: one read rule for a page — View on it and on every ancestor.
 */
final class WikiPageVisibilityTest extends TestCase
{
    public function testEveryAncestorMustBeReadable(): void
    {
        $space     = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $vault     = new WikiPage($space, 'vault', 'Vault');
        $plan      = new WikiPage($space, 'plan', 'Plan', $vault);
        $leaf      = new WikiPage($space, 'leaf', 'Leaf', $plan);
        $user      = new TestUser();
        $hideVault = new CallbackSpaceAccessChecker(static fn (WikiAction $a, WikiSpace $s, ?WikiPage $p): bool => $p !== $vault);

        self::assertTrue(WikiPageVisibility::isReadable(CallbackSpaceAccessChecker::allowing(WikiAction::View), $user, $space, $leaf));
        self::assertFalse(WikiPageVisibility::isReadable($hideVault, $user, $space, $leaf));
        self::assertFalse(WikiPageVisibility::isReadable($hideVault, $user, $space, $vault));

        $guard = new WikiSpaceGuard(new FixedSpaceLocator($space), $hideVault);
        self::assertFalse($guard->isPageReadable($user, $space, $plan));
        $guard->requireReadablePage($user, $space, new WikiPage($space, 'other', 'Other'));
        $this->expectException(NotFoundHttpException::class);
        $guard->requireReadablePage($user, $space, $plan);
    }

    public function testAParentCycleReadsAsHidden(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $a     = new WikiPage($space, 'a', 'A');
        $b     = new WikiPage($space, 'b', 'B', $a);
        (new ReflectionProperty(WikiPage::class, 'parent'))->setValue($a, $b);

        self::assertFalse(WikiPageVisibility::isReadable(CallbackSpaceAccessChecker::allowing(WikiAction::View), new TestUser(), $space, $a));
    }
}
