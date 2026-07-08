<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\ValueObject;

use InvalidArgumentException;
use Nowo\WikiBundle\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidInvalidTest extends TestCase
{
    public function testRejectsInvalidUuid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Uuid::fromString('not-a-uuid');
    }
}
