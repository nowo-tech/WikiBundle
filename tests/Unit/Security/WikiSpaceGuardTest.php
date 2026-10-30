<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Nowo\WikiBundle\Security\WikiSpaceGuard;
use Nowo\WikiBundle\Service\WikiSpaceLocatorInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

use function array_slice;
use function in_array;

final class WikiSpaceGuardTest extends TestCase
{
    public function testReadableSpaceIsReturned(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');

        self::assertSame($space, $this->guard($space, [WikiAction::View])->requireSpace(new TestUser(), 'eng'));
    }

    public function testMissingSpaceIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->guard(null, [WikiAction::View])->requireSpace(new TestUser(), 'eng');
    }

    public function testUnreadableSpaceIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->guard(new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't'), [])->requireSpace(new TestUser(), 'eng');
    }

    public function testDeniedWriteIsForbiddenAndDeniedViewIsNotFound(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $page  = new WikiPage($space, 'p', 'P');
        $guard = $this->guard($space, [WikiAction::Edit]);

        $guard->requireGranted(new TestUser(), WikiAction::Edit, $space, $page);
        self::assertTrue($guard->isGranted(new TestUser(), WikiAction::Edit, $space, $page));
        self::assertSame(['view' => false, 'create' => false, 'edit' => true], array_slice($guard->grants(new TestUser(), $space, $page), 0, 3));

        try {
            $guard->requireGranted(new TestUser(), WikiAction::View, $space, $page);
            self::fail('View must be denied with a 404.');
        } catch (NotFoundHttpException) {
        }

        $this->expectException(AccessDeniedHttpException::class);
        $guard->requireGranted(new TestUser(), WikiAction::Manage, $space);
    }

    /**
     * @param list<WikiAction> $allowed
     */
    private function guard(?WikiSpace $space, array $allowed): WikiSpaceGuard
    {
        $locator = $this->createStub(WikiSpaceLocatorInterface::class);
        $locator->method('locate')->willReturn($space);

        $checker = new class($allowed) implements WikiSpaceAccessCheckerInterface {
            /** @param list<WikiAction> $allowed */
            public function __construct(private readonly array $allowed)
            {
            }

            public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
            {
                return in_array($action, $this->allowed, true);
            }
        };

        return new WikiSpaceGuard($locator, $checker);
    }
}
