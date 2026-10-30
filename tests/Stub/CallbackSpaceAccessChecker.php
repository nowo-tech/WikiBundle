<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

use Closure;
use Nowo\WikiBundle\Entity\WikiPage;
use Nowo\WikiBundle\Entity\WikiSpace;
use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\Security\WikiSpaceAccessCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use function in_array;

/**
 * Space checker deciding with a closure (or a fixed list of allowed actions).
 */
final readonly class CallbackSpaceAccessChecker implements WikiSpaceAccessCheckerInterface
{
    /**
     * @param Closure(WikiAction, WikiSpace, ?WikiPage): bool $decide
     */
    public function __construct(private Closure $decide)
    {
    }

    /**
     * @param list<WikiAction> $allowed
     */
    public static function allowing(WikiAction ...$allowed): self
    {
        return new self(static fn (WikiAction $action): bool => in_array($action, $allowed, true));
    }

    public function isGranted(UserInterface $user, WikiAction $action, WikiSpace $space, ?WikiPage $page = null): bool
    {
        return ($this->decide)($action, $space, $page);
    }
}
