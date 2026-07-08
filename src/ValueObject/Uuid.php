<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use InvalidArgumentException;
use Stringable;

use function chr;
use function ord;
use function sprintf;

/**
 * Immutable UUID value object (RFC 4122).
 */
final readonly class Uuid implements Stringable
{
    private function __construct(private string $value)
    {
        if (!$this->isValid($value)) {
            throw new InvalidArgumentException(sprintf('Invalid UUID: %s', $value));
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public static function generate(): self
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function isValid(string $value): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        );
    }
}
