<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Api;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

use function array_is_list;
use function count;
use function intdiv;
use function is_array;
use function is_string;
use function preg_match;
use function strlen;

/**
 * 1.24 (spec 218): the query string of a read, validated: a value of the wrong form is 400 `INPUT_002` naming it, never a
 * silent default. Pages count from 0, 100 rows each.
 */
final readonly class WikiApiQuery
{
    public const PAGE_SIZE = 100;

    /** The deepest page a client may ask (10 000 pages of 100). */
    public const MAX_PAGE = 9999;

    public function __construct(private Request $request)
    {
    }

    /** @throws WikiApiRefusal */
    public function page(): int
    {
        $value = $this->scalar('page');
        if ($value === null) {
            return 0;
        }
        if (preg_match('/^\d{1,4}$/D', $value) !== 1) {
            throw WikiApiRefusal::invalid('page');
        }

        return (int) $value;
    }

    /** @throws WikiApiRefusal */
    public function bool(string $name): ?bool
    {
        return match ($this->scalar($name)) {
            null         => null,
            'true', '1'  => true,
            'false', '0' => false,
            default      => throw WikiApiRefusal::invalid($name),
        };
    }

    /** @throws WikiApiRefusal */
    public function string(string $name, int $maxLength = 255): ?string
    {
        $value = $this->scalar($name);
        if ($value !== null && ($value === '' || strlen($value) > $maxLength || preg_match('//u', $value) !== 1)) {
            throw WikiApiRefusal::invalid($name);
        }

        return $value;
    }

    /**
     * Unix milliseconds.
     *
     * @throws WikiApiRefusal
     */
    public function date(string $name): ?DateTimeImmutable
    {
        $value = $this->scalar($name);
        if ($value === null) {
            return null;
        }
        if (preg_match('/^\d{1,15}$/D', $value) !== 1) {
            throw WikiApiRefusal::invalid($name);
        }

        return new DateTimeImmutable('@' . intdiv((int) $value, 1000));
    }

    /**
     * A repeatable parameter (`wiki=a&wiki=b` or `wiki[]=a`).
     *
     * @throws WikiApiRefusal
     *
     * @return list<string>
     */
    public function strings(string $name, int $max = 50): array
    {
        $raw    = $this->request->query->all()[$name] ?? null;
        $values = match (true) {
            $raw === null                         => [],
            is_string($raw)                       => [$raw],
            is_array($raw) && array_is_list($raw) => $raw,
            default                               => throw WikiApiRefusal::invalid($name),
        };
        if (count($values) > $max) {
            throw WikiApiRefusal::invalid($name);
        }
        $strings = [];
        foreach ($values as $value) {
            $strings[] = is_string($value) && $value !== '' && strlen($value) <= 255 ? $value : throw WikiApiRefusal::invalid($name);
        }

        return $strings;
    }

    /** @throws WikiApiRefusal */
    private function scalar(string $name): ?string
    {
        // all(): an array value never reaches a string check (InputBag::get() would throw a 400 of its own).
        $value = $this->request->query->all()[$name] ?? null;

        return $value === null || is_string($value) ? $value : throw WikiApiRefusal::invalid($name);
    }
}
