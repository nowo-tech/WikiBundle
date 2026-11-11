<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use JsonException;
use Symfony\Component\HttpFoundation\Request;

use function array_diff_key;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function mb_strlen;
use function preg_match;
use function str_contains;
use function strlen;
use function trim;

use const JSON_BIGINT_AS_STRING;
use const JSON_THROW_ON_ERROR;

/**
 * 1.24 (spec 218): a write's JSON body. Each field an operation reads is marked; what nobody read is listed in the
 * answer's `warnings` as `unknown`. A value of the wrong type is a 400 naming the field, never a silent default. Strings
 * must be valid UTF-8 without NUL.
 */
final class WikiApiInput
{
    /** @var array<string, true> */
    private array $read = [];

    /** @var array<string, true> fields the operation knows and does not support */
    private array $ignored = [];

    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(private readonly array $fields)
    {
    }

    /**
     * @throws WikiApiRefusal
     */
    public static function fromRequest(Request $request, int $maxBytes): self
    {
        $content = (string) $request->getContent();
        if (strlen($content) > $maxBytes) {
            throw new WikiApiRefusal(413, 'INPUT_003', 'Body too large');
        }
        if (trim($content) === '') {
            return new self([]);
        }
        // Audit IF-I11: JSON only, so a form a browser could send never counts as a write's body.
        if (preg_match('#^application/(?:[a-z0-9.+-]+\+)?json(?:\s*;.*)?$#i', (string) $request->headers->get('Content-Type')) !== 1) {
            throw new WikiApiRefusal(415, 'INPUT_005', 'The body must be application/json');
        }
        try {
            $decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw new WikiApiRefusal(400, 'INPUT_001', 'Malformed JSON');
        }
        if (!is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new WikiApiRefusal(400, 'INPUT_001', 'The body must be a JSON object');
        }

        /* @var array<string, mixed> $decoded */
        return new self($decoded);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->fields);
    }

    /**
     * @throws WikiApiRefusal
     */
    public function string(string $name, bool $required = false, int $maxLength = 0): ?string
    {
        $value = $this->take($name);
        if ($value === null) {
            return $required ? throw WikiApiRefusal::invalid($name) : null;
        }
        if (!is_string($value) || preg_match('//u', $value) !== 1 || str_contains($value, "\0") || ($maxLength > 0 && mb_strlen($value) > $maxLength)) {
            throw WikiApiRefusal::invalid($name);
        }

        return $value;
    }

    /**
     * @throws WikiApiRefusal
     */
    public function bool(string $name): ?bool
    {
        $value = $this->take($name);

        return $value === null || is_bool($value) ? $value : throw WikiApiRefusal::invalid($name);
    }

    /**
     * @throws WikiApiRefusal
     */
    public function int(string $name, int $min = 0, int $max = 2147483647): ?int
    {
        $value = $this->take($name);
        if ($value === null) {
            return null;
        }

        return is_int($value) && $value >= $min && $value <= $max ? $value : throw WikiApiRefusal::invalid($name);
    }

    /** A field this operation knows and ignores: listed in `warnings` as `not_supported` when sent. */
    public function ignore(string $name): void
    {
        if ($this->has($name)) {
            // @igor-ignore - Per-request value object (WikiApiInput::fromRequest), never a shared service.
            $this->ignored[$name] = true;
        }
        // @igor-ignore - Per-request value object (WikiApiInput::fromRequest), never a shared service.
        $this->read[$name] = true;
    }

    /**
     * The fields nobody read, and the ignored ones that were sent.
     *
     * @return list<array{field: string, reason: string}>
     */
    public function warnings(): array
    {
        $warnings = [];
        foreach (array_keys($this->ignored) as $field) {
            $warnings[] = ['field' => $field, 'reason' => 'not_supported'];
        }
        foreach (array_keys(array_diff_key($this->fields, $this->read)) as $field) {
            $warnings[] = ['field' => (string) $field, 'reason' => 'unknown'];
        }

        return $warnings;
    }

    private function take(string $name): mixed
    {
        // @igor-ignore - Per-request value object (WikiApiInput::fromRequest), never a shared service.
        $this->read[$name] = true;

        return $this->fields[$name] ?? null;
    }
}
