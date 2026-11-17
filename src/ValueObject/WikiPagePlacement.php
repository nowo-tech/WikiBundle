<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\ValueObject;

use function explode;
use function in_array;
use function is_string;
use function preg_match;
use function str_contains;
use function strlen;
use function trim;

/**
 * 1.35: where the new-page form asks for a new page (`web_ui.new_page_dialog`), as an intent the server turns into a
 * place (audit PT-L3: the browser never sends an index): `child:<page id>` (last among that page's children),
 * `above:<page id>` or `below:<page id>` (right next to that page, under its parent). Framework-free and immutable.
 */
final readonly class WikiPagePlacement
{
    public const CHILD = 'child';

    public const ABOVE = 'above';

    public const BELOW = 'below';

    /**
     * @param string $kind {@see self::CHILD}, {@see self::ABOVE} or {@see self::BELOW}
     * @param string $pageId id of the page the place is relative to
     *
     * @throws WikiInvalidPlacementException when the kind is unknown or the id is not a page id
     */
    public function __construct(
        public string $kind,
        public string $pageId,
    ) {
        if (!in_array($kind, [self::CHILD, self::ABOVE, self::BELOW], true)) {
            throw new WikiInvalidPlacementException('Invalid page place.');
        }
        if ($pageId === '' || strlen($pageId) > 36 || preg_match('/^[A-Za-z0-9-]+$/D', $pageId) !== 1) {
            throw new WikiInvalidPlacementException('Invalid page id.');
        }
    }

    /**
     * Reads the raw value of the form field or the query (`kind:id`); null when nothing was asked.
     *
     * @throws WikiInvalidPlacementException when the value is not such an intent
     */
    public static function fromValue(mixed $value): ?self
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (!is_string($value) || !str_contains($value, ':')) {
            throw new WikiInvalidPlacementException('Invalid page place.');
        }
        [$kind, $pageId] = explode(':', trim($value), 2);

        return new self($kind, $pageId);
    }

    /** As a child of the page (its last place); else next to it. */
    public function isChild(): bool
    {
        return $this->kind === self::CHILD;
    }

    /** Below the page (else above it); meaningless for a child. */
    public function isAfter(): bool
    {
        return $this->kind === self::BELOW;
    }

    /** The form / query value. */
    public function value(): string
    {
        return $this->kind . ':' . $this->pageId;
    }
}
