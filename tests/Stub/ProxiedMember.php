<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Stub;

/**
 * Spec 234: a user class with an id, the parent of a Doctrine-style proxy declared by the member reference test.
 */
class ProxiedMember
{
    public function getId(): string
    {
        return 'p-1';
    }
}
