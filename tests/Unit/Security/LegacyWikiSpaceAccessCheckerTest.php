<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\Security;

use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Enum\WikiSpaceOwnerScope;
use Nowo\WikiBundle\Security\LegacyWikiSpaceAccessChecker;
use Nowo\WikiBundle\Security\WikiAccessCheckerInterface;
use Nowo\WikiBundle\Service\WikiSpaceAccessResolverInterface;
use Nowo\WikiBundle\Tests\Stub\TestUser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
}
