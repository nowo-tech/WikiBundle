<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Support;

use Closure;
use Nowo\WikiBundle\Port\Internal\WikiAccessCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The list gate of the link picker tests, decided by a closure.
 */
final readonly class LinkPickerTestAccess implements WikiAccessCheckerInterface
{
    /**
     * @param Closure(): bool $canList
     */
    public function __construct(private Closure $canList)
    {
    }

    public function canAccess(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canList(?UserInterface $user = null): bool
    {
        return ($this->canList)();
    }

    public function canCreate(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canEdit(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canViewHistory(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canArchive(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canAskAi(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canImport(?UserInterface $user = null): bool
    {
        return true;
    }

    public function canExport(?UserInterface $user = null): bool
    {
        return true;
    }
}
