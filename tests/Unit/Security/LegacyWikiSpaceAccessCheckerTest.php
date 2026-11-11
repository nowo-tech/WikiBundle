<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use ArrayObject;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LegacyWikiSpaceAccessCheckerTest extends TestCase
{
    /**
     * @return iterable<string, array{WikiAction, string}>
     */
    public static function actions(): iterable
    {
        yield 'create' => [WikiAction::Create, 'canCreate'];
        yield 'edit' => [WikiAction::Edit, 'canEdit'];
        yield 'archive' => [WikiAction::Archive, 'canArchive'];
        yield 'history' => [WikiAction::History, 'canViewHistory'];
        yield 'import' => [WikiAction::Import, 'canImport'];
        yield 'manage' => [WikiAction::Manage, 'canImport'];
        yield 'export' => [WikiAction::Export, 'canExport'];
        yield 'ask_ai' => [WikiAction::AskAi, 'canAskAi'];
        yield 'delete (1.14 trash)' => [WikiAction::Delete, 'canArchive'];
        yield 'purge (1.14 trash)' => [WikiAction::Purge, 'canImport'];
    }

    #[DataProvider('actions')]
    public function testActionMapsOntoTheUserLevelCheck(WikiAction $action, string $method): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');

        self::assertTrue($this->checker($space, [$method => true])->isGranted(new TestUser(), $action, $space));
        self::assertFalse($this->checker($space, [$method => false])->isGranted(new TestUser(), $action, $space));
    }

    public function testViewAndRestore(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');

        self::assertTrue($this->checker($space, [])->isGranted(new TestUser(), WikiAction::View, $space));
        self::assertTrue($this->checker($space, ['canEdit' => true, 'canViewHistory' => true])->isGranted(new TestUser(), WikiAction::Restore, $space));
        self::assertFalse($this->checker($space, ['canEdit' => true])->isGranted(new TestUser(), WikiAction::Restore, $space));
    }

    public function testCommentActionsAreDeniedByDefault(): void
    {
        // 1.16: default deny — even a user holding every 1.x role gets no comment right from the legacy mapping.
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $all   = ['canAccess' => true, 'canList' => true, 'canCreate' => true, 'canEdit' => true, 'canViewHistory' => true, 'canArchive' => true, 'canAskAi' => true, 'canImport' => true, 'canExport' => true];

        foreach (WikiAction::commentActions() as $action) {
            self::assertFalse($this->checker($space, $all)->isGranted(new TestUser(), $action, $space), $action->value);
        }
    }

    public function testASpaceOutsideTheUsersSpacesIsDenied(): void
    {
        $space = new WikiSpace('eng', 'Eng', WikiSpaceOwnerScope::Team, 't');
        $other = new WikiSpace('ops', 'Ops', WikiSpaceOwnerScope::Team, 't');

        self::assertFalse($this->checker($other, ['canEdit' => true])->isGranted(new TestUser(), WikiAction::View, $space));
    }

    /**
     * @param array<string, bool> $grants
     */
    private function checker(WikiSpace $member, array $grants): LegacyWikiSpaceAccessChecker
    {
        $resolver = $this->createStub(WikiSpaceAccessResolverInterface::class);
        $resolver->method('listSpacesForUser')->willReturn([$member]);
        $checker = $this->createStub(WikiAccessCheckerInterface::class);
        foreach ($grants as $method => $value) {
            $checker->method($method)->willReturn($value);
        }

        return new LegacyWikiSpaceAccessChecker($checker, $resolver);
    }

    public function testMembershipsAreReadOncePerUserUntilReset(): void
    {
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $other    = new WikiSpace('hr', 'HR', WikiSpaceOwnerScope::Team, 't');
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->expects(self::exactly(4))->method('listSpacesForUser')->willReturn([$space]);
        $checker = new LegacyWikiSpaceAccessChecker($this->createStub(WikiAccessCheckerInterface::class), $resolver, self::requestStack());
        $ana     = new TestUser('u1', 'ana@x.test');

        for ($i = 0; $i < 500; ++$i) {
            self::assertTrue($checker->isGranted($ana, WikiAction::View, $space));
        }

        self::assertFalse($checker->isGranted($ana, WikiAction::View, $other));
        $bob = new TestUser('u2', 'bob@x.test');
        self::assertTrue($checker->isGranted($bob, WikiAction::View, $space), 'another user is read on its own');
        $twin = new TestUser('u1', 'ana@x.test');
        self::assertTrue($checker->isGranted($twin, WikiAction::View, $space), 'CV-I9: another user object with the same identifier is read on its own');

        $checker->reset();
        self::assertTrue($checker->isGranted($ana, WikiAction::View, $space), 'read again after kernel.reset');
    }

    public function testTheMemoIsBounded(): void
    {
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->expects(self::exactly(LegacyWikiSpaceAccessChecker::MAX_MEMO + 2))->method('listSpacesForUser')->willReturn([$space]);
        $checker = new LegacyWikiSpaceAccessChecker($this->createStub(WikiAccessCheckerInterface::class), $resolver, self::requestStack());
        $users   = [];
        for ($i = 0; $i <= LegacyWikiSpaceAccessChecker::MAX_MEMO; ++$i) {
            $users[] = new TestUser('u' . $i, 'u' . $i . '@x.test');
            self::assertTrue($checker->isGranted($users[$i], WikiAction::View, $space));
        }

        self::assertTrue($checker->isGranted($users[0], WikiAction::View, $space), 'emptied past MAX_MEMO: read again');
    }

    public function testANewMainRequestReadsTheMembershipsAgain(): void
    {
        // 1.29.1 (REQ-RUNTIME-002): a worker that never runs kernel.reset hands the same user object (kept managed by
        // the host's EntityManager) to the next request; the membership revoked in between must not be served.
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $member   = new ArrayObject([true]);
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->expects(self::exactly(2))->method('listSpacesForUser')->willReturnCallback(static fn (): array => $member[0] ? [$space] : []);
        $stack   = self::requestStack();
        $checker = new LegacyWikiSpaceAccessChecker($this->createStub(WikiAccessCheckerInterface::class), $resolver, $stack);
        $ana     = new TestUser('u1', 'ana@x.test');

        self::assertTrue($checker->isGranted($ana, WikiAction::View, $space));
        $member[0] = false;
        self::assertTrue($checker->isGranted($ana, WikiAction::View, $space), 'memoised within one request');

        $stack->pop();
        $stack->push(Request::create('/next'));
        self::assertFalse($checker->isGranted($ana, WikiAction::View, $space), 'the next request reads the revocation');
    }

    public function testNothingIsMemoisedOutsideARequest(): void
    {
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $member   = new ArrayObject([true]);
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->expects(self::exactly(4))->method('listSpacesForUser')->willReturnCallback(static fn (): array => $member[0] ? [$space] : []);
        $ana = new TestUser('u1', 'ana@x.test');

        foreach ([new RequestStack(), null] as $stack) {
            $member[0] = true;
            $checker   = new LegacyWikiSpaceAccessChecker($this->createStub(WikiAccessCheckerInterface::class), $resolver, $stack);
            self::assertTrue($checker->isGranted($ana, WikiAction::View, $space));
            $member[0] = false;
            self::assertFalse($checker->isGranted($ana, WikiAction::View, $space), 'console / Messenger: read on every call');
        }
    }

    public function testResetForgetsTheRequestToo(): void
    {
        $space    = new WikiSpace('eng', 'Engineering', WikiSpaceOwnerScope::Team, 't');
        $member   = new ArrayObject([true]);
        $resolver = $this->createMock(WikiSpaceAccessResolverInterface::class);
        $resolver->expects(self::exactly(2))->method('listSpacesForUser')->willReturnCallback(static fn (): array => $member[0] ? [$space] : []);
        $checker = new LegacyWikiSpaceAccessChecker($this->createStub(WikiAccessCheckerInterface::class), $resolver, self::requestStack());
        $ana     = new TestUser('u1', 'ana@x.test');

        self::assertTrue($checker->isGranted($ana, WikiAction::View, $space));
        $member[0] = false;
        $checker->reset();
        self::assertFalse($checker->isGranted($ana, WikiAction::View, $space), 'read again after reset() in the same request');
    }

    private static function requestStack(): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        return $stack;
    }
}
