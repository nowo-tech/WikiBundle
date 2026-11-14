<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use InvalidArgumentException;
use Nowo\WikiBundle\Service\WikiPageMover;

use function ctype_digit;
use function is_string;
use function preg_match;
use function strlen;
use function trim;

/**
 * 1.35: where a new page goes (the new-page form of `web_ui.new_page_dialog`): the parent page id (null: the root)
 * and the 1-based position among the parent's children the actor sees (null: no place asked, the page is created as
 * before 1.35). Positions follow the "Move" form: 1 is the first place, a number past the last child is the last place.
 * Framework-free and immutable.
 */
final readonly class WikiPagePlacement
{
    /** Largest position accepted (the mover clamps to the last place anyway). */
    public const MAX_POSITION = 100000;

    /**
     * @param string|null $parentId id of the parent page, or null for the root
     * @param int|null $position 1-based position, or null to append as before 1.35
     *
     * @throws InvalidArgumentException when the parent id is empty or too long, or the position is out of range
     */
    public function __construct(
        public ?string $parentId = null,
        public ?int $position = null,
    ) {
        if ($parentId !== null && ($parentId === '' || strlen($parentId) > 36 || preg_match('/^[A-Za-z0-9-]+$/D', $parentId) !== 1)) {
            throw new InvalidArgumentException('Invalid parent page id.');
        }
        if ($position !== null && ($position < 1 || $position > self::MAX_POSITION)) {
            throw new InvalidArgumentException('Invalid page position.');
        }
    }

    /**
     * Reads the raw values of a form or a query (strings, empty = not given).
     *
     * @throws InvalidArgumentException when a value is not a page id / a positive integer
     */
    public static function fromValues(mixed $parentId, mixed $position): self
    {
        $parent = is_string($parentId) && trim($parentId) !== '' ? trim($parentId) : null;
        if ($parentId !== null && !is_string($parentId)) {
            throw new InvalidArgumentException('Invalid parent page id.');
        }
        if ($position === null || $position === '') {
            return new self($parent);
        }
        if (!is_string($position) || strlen($position) > 6 || !ctype_digit($position)) {
            throw new InvalidArgumentException('Invalid page position.');
        }

        return new self($parent, (int) $position);
    }

    /** Whether a place among the siblings was asked (else the page is created as before 1.35). */
    public function isPositioned(): bool
    {
        return $this->position !== null;
    }

    /** The 0-based index of the mover ({@see WikiPageMover::createAt()}). */
    public function index(): int
    {
        return ($this->position ?? 1) - 1;
    }
}
