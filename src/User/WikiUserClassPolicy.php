<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\User;

use Stringable;

use function in_array;
use function is_int;
use function is_numeric;
use function is_string;
use function ltrim;
use function method_exists;
use function trim;

/**
 * Which security users the wiki maps to a stored `user_class` row by their `getId()` (1.27, audit JX-W1).
 *
 * `nowo_wiki.user_class` instances always are. Another class is only when listed in `nowo_wiki.accepted_user_classes`
 * (a wrapper / read model carrying the `user_class` id). With the default empty list a user of another identity
 * domain (Admin#5 next to Customer#5) is never stored as `user_class`#5 and never owns `user_class`#5's comments: those
 * paths fail closed. Ids of 0, negative ids and empty ids are refused.
 */
final readonly class WikiUserClassPolicy
{
    /** @var list<string> */
    private array $acceptedUserClasses;

    /**
     * @param string $userClass `nowo_wiki.user_class`
     * @param list<string> $acceptedUserClasses `nowo_wiki.accepted_user_classes`
     */
    public function __construct(
        private string $userClass,
        array $acceptedUserClasses = [],
    ) {
        $classes = [];
        foreach ($acceptedUserClasses as $class) {
            $classes[] = ltrim($class, '\\');
        }
        $this->acceptedUserClasses = $classes;
    }

    /**
     * Whether the user is a `user_class` instance.
     */
    public function isCreatorClass(object $user): bool
    {
        $class = ltrim($this->userClass, '\\');

        return $user instanceof $class;
    }

    /**
     * Whether the user is a `user_class` instance or an instance of a listed accepted class.
     */
    public function isAccepted(object $user): bool
    {
        if ($this->isCreatorClass($user)) {
            return true;
        }
        if ($user instanceof WikiRefUser) {
            // 1.32: a framework-free host's reference stands for its class: accepted only when it names user_class or
            // a listed class exactly (no subclass or look-alike match).
            $class = ltrim($user->getUserClass(), '\\');

            return $class === ltrim($this->userClass, '\\') || in_array($class, $this->acceptedUserClasses, true);
        }

        foreach ($this->acceptedUserClasses as $class) {
            if ($user instanceof $class) {
                return true;
            }
        }

        return false;
    }

    /**
     * The raw `getId()` of an accepted user when it is a usable id (positive int, non-empty string or Stringable that
     * is not a numeric value ≤ 0), else null.
     */
    public function idOf(object $user): int|string|Stringable|null
    {
        if (!$this->isAccepted($user) || !method_exists($user, 'getId')) {
            return null;
        }

        $id = $user->getId();

        return self::isUsableId($id) ? $id : null;
    }

    /**
     * Whether an id value can identify a user: a positive int, or a non-empty string / Stringable that is not a
     * numeric value ≤ 0.
     */
    public static function isUsableId(mixed $id): bool
    {
        if (is_int($id)) {
            return $id > 0;
        }
        if (!is_string($id) && !$id instanceof Stringable) {
            return false;
        }
        $value = trim((string) $id);

        return $value !== '' && (!is_numeric($value) || (float) $value > 0);
    }
}
