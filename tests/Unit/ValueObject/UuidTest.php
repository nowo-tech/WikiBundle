<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Tests\Unit\ValueObject;

use Nowo\WikiBundle\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testGenerateAndToString(): void
    {
        $uuid = Uuid::generate();
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid->toString());
    }

    public function testFromString(): void
    {
        $value = '550e8400-e29b-41d4-a716-446655440000';
        self::assertSame($value, Uuid::fromString($value)->toString());
    }

    public function testToStringCast(): void
    {
        $uuid = Uuid::fromString('550e8400-e29b-41d4-a716-446655440000');
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', (string) $uuid);
    }
}
