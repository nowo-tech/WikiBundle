<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Transaction;

use function str_contains;
use function str_starts_with;

/**
 * Tells the wiki's entity classes (and their Doctrine proxies) from the application's. 2.0: only this helper is left
 * (the 1.37 flush observer of the removed shared entity manager is gone).
 *
 * @internal
 */
final class WikiFlushObserver
{
    /** Namespace of the wiki's entities. */
    private const ENTITY_NAMESPACE = 'Nowo\\WikiBundle\\Entity\\';

    /** Whether a class is one of the wiki's entities (its Doctrine proxy included). */
    public static function isWikiEntity(string $class): bool
    {
        return str_starts_with($class, self::ENTITY_NAMESPACE) || str_contains($class, '\\__CG__\\' . self::ENTITY_NAMESPACE);
    }
}
