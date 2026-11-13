<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Security;

use Nowo\WikiBundle\Enum\WikiAction;
use Nowo\WikiBundle\ValueObject\WikiPageSnapshot;
use Nowo\WikiBundle\ValueObject\WikiSpaceSnapshot;
use Nowo\WikiBundle\ValueObject\WikiUserRef;

/**
 * Per-space and per-page rights, framework-free (1.33.0): the twin of the deprecated
 * {@see WikiSpaceAccessCheckerInterface} (alias either interface to your service).
 */
interface WikiSpaceAccessPolicyInterface
{
    /**
     * @param WikiUserRef $user the user
     * @param WikiAction $action the action asked for
     * @param WikiSpaceSnapshot $space the space
     * @param WikiPageSnapshot|null $page the page, for page-level actions
     *
     * @return bool true to allow
     */
    public function isGranted(WikiUserRef $user, WikiAction $action, WikiSpaceSnapshot $space, ?WikiPageSnapshot $page = null): bool;
}
