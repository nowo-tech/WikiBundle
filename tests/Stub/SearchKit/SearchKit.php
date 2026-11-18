<?php

declare(strict_types=1);

/*
 * 1.44 (Beacon KIT-85): the SearchKitBundle (>= 1.8) ports and value objects the optional wiki provider
 * (nowo_wiki.search_kit_provider) implements and builds, with their 1.8.1 signatures, for the tests and PHPStan only:
 * SearchKit is not a dependency (it needs PHP 8.3, the wiki runs on 8.2). The real classes win when they are installed
 * (the CI job "SearchKit bridge" runs the same tests against the real SearchKit 1.8). SearchQuery::score() is a plain
 * case-insensitive stand-in for SearchKit's folding matcher.
 */

namespace Nowo\SearchKitBundle\Contract {
    use Nowo\SearchKitBundle\Model\SearchGroup;
    use Nowo\SearchKitBundle\Model\SearchItem;
    use Nowo\SearchKitBundle\Model\SearchQuery;
    use Nowo\SearchKitBundle\Model\SearchReference;

    use function interface_exists;

    if (!interface_exists(SearchProviderInterface::class)) {
        interface SearchProviderInterface
        {
            public const TAG = 'nowo_search_kit.provider';

            public function search(SearchQuery $query): ?SearchGroup;
        }
    }

    if (!interface_exists(IdentifierAwareSearchProviderInterface::class)) {
        interface IdentifierAwareSearchProviderInterface extends SearchProviderInterface
        {
        }
    }

    if (!interface_exists(FilterableSearchProviderInterface::class)) {
        interface FilterableSearchProviderInterface extends SearchProviderInterface
        {
            /**
             * @return list<string>
             */
            public function supportedFilters(): array;
        }
    }

    if (!interface_exists(PaginatedSearchProviderInterface::class)) {
        interface PaginatedSearchProviderInterface extends SearchProviderInterface
        {
            public function groupKey(): string;

            public function searchPage(SearchQuery $query, int $page, int $perPage): ?SearchGroup;
        }
    }

    if (!interface_exists(SearchReferenceResolverInterface::class)) {
        interface SearchReferenceResolverInterface
        {
            public const REFERENCE_RESOLVER_TAG = 'nowo_search_kit.reference_resolver';

            public function resolve(SearchReference $reference, string $userIdentifier, ?string $locale): ?SearchItem;
        }
    }
}

namespace Nowo\SearchKitBundle\Model {
    use DateTimeImmutable;
    use InvalidArgumentException;
    use Symfony\Component\Security\Core\User\UserInterface;

    use function array_filter;
    use function array_map;
    use function array_values;
    use function class_exists;
    use function mb_stripos;
    use function mb_strtolower;
    use function preg_match;
    use function str_starts_with;

    if (!class_exists(SearchTerm::class)) {
        final readonly class SearchTerm
        {
            public function __construct(
                public string $text,
                public ?string $locale = null,
                public bool $synonym = false,
            ) {
            }
        }
    }

    if (!class_exists(SearchFilter::class)) {
        final readonly class SearchFilter
        {
            public function __construct(
                public string $key,
                public string $value,
                public bool $negated = false,
            ) {
                if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $key) !== 1 || $value === '') {
                    throw new InvalidArgumentException('Invalid search filter.');
                }
            }
        }
    }

    if (!class_exists(SearchReference::class)) {
        final readonly class SearchReference
        {
            /**
             * @param array<string, scalar|null> $routeParameters
             */
            public function __construct(
                public string $url,
                public string $kind,
                public string $route,
                public array $routeParameters = [],
            ) {
            }
        }
    }

    if (!class_exists(SearchBadge::class)) {
        final readonly class SearchBadge
        {
            public function __construct(public string $label, public string $tone = 'neutral')
            {
            }

            /**
             * @return array{label: string, tone: string}
             */
            public function toArray(): array
            {
                return ['label' => $this->label, 'tone' => $this->tone];
            }
        }
    }

    if (!class_exists(SearchItem::class)) {
        final readonly class SearchItem
        {
            public function __construct(
                public string $title,
                public string $url,
                public string $subtitle = '',
                public ?string $icon = null,
                public ?SearchBadge $badge = null,
                public ?string $kind = null,
                public ?float $score = null,
                public ?DateTimeImmutable $date = null,
                public ?string $shortcut = null,
            ) {
                if (!self::isSafeUrl($url)) {
                    throw new InvalidArgumentException('A search item URL must be a path starting with "/" or an http(s) URL.');
                }
            }

            public static function isSafeUrl(string $url): bool
            {
                if (preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
                    return false;
                }
                if (str_starts_with($url, '/')) {
                    return !str_starts_with($url, '//') && !str_starts_with($url, '/\\');
                }

                return preg_match('#^https?://[^\s/]+#i', $url) === 1;
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(string $groupKey, ?string $iconSvg = null): array
            {
                return ['title' => $this->title, 'subtitle' => $this->subtitle, 'url' => $this->url, 'kind' => $this->kind ?? $groupKey, 'icon' => $iconSvg, 'badge' => $this->badge?->toArray()];
            }
        }
    }

    if (!class_exists(SearchGroup::class)) {
        final readonly class SearchGroup
        {
            /**
             * @param list<SearchItem> $items
             */
            public function __construct(
                public string $key,
                public string $label,
                public array $items,
                public ?int $total = null,
                public ?string $moreUrl = null,
                public ?string $moreLabel = null,
                public bool $exactTotal = false,
            ) {
            }

            public function limit(int $limit): self
            {
                return \count($this->items) <= $limit ? $this : new self($this->key, $this->label, \array_slice($this->items, 0, $limit), $this->total ?? \count($this->items), $this->moreUrl, $this->moreLabel, $this->exactTotal);
            }

            /**
             * @return array<string, mixed>
             */
            public function toArray(): array
            {
                return [
                    'key'   => $this->key,
                    'label' => $this->label,
                    'total' => $this->total ?? \count($this->items),
                    'items' => array_map(fn (SearchItem $item): array => $item->toArray($this->key), $this->items),
                    'more'  => $this->moreUrl === null ? null : ['url' => $this->moreUrl, 'label' => $this->moreLabel ?? $this->moreUrl],
                ];
            }
        }
    }

    if (!class_exists(SearchQuery::class)) {
        final readonly class SearchQuery
        {
            public string $userIdentifier;

            /** @var list<SearchFilter> */
            public array $filters;

            /** @var list<SearchTerm> */
            private array $alternatives;

            /**
             * @param list<SearchFilter> $filters
             * @param list<SearchTerm> $terms
             */
            public function __construct(
                public string $text,
                public ?UserInterface $user,
                public int $limit,
                public ?string $scope = null,
                public bool $belowMinLength = false,
                ?string $userIdentifier = null,
                array $filters = [],
                public ?string $locale = null,
                array $terms = [],
                public bool $fuzzy = false,
            ) {
                $this->filters        = array_values($filters);
                $this->alternatives   = array_values($terms);
                $this->userIdentifier = $userIdentifier ?? (string) $user?->getUserIdentifier();
                if ($this->userIdentifier === '') {
                    throw new InvalidArgumentException('A search query needs the user identifier.');
                }
            }

            public static function forUserIdentifier(string $userIdentifier, string $text, int $limit, ?string $scope = null, bool $belowMinLength = false): self
            {
                return new self($text, null, $limit, $scope, $belowMinLength, $userIdentifier);
            }

            public function matches(string ...$haystacks): bool
            {
                return $this->score(...$haystacks) !== null;
            }

            /** Stand-in: 1.0 for an equal text, 0.8 for a prefix, 0.6 for a substring, null otherwise. */
            public function score(string ...$haystacks): ?float
            {
                $best = null;
                foreach ($this->terms() as $term) {
                    foreach ($haystacks as $haystack) {
                        $position = $term->text === '' ? false : mb_stripos($haystack, $term->text);
                        if ($position === false) {
                            continue;
                        }
                        $score = mb_strtolower($haystack) === mb_strtolower($term->text) ? 1.0 : ($position === 0 ? 0.8 : 0.6);
                        $best  = $best === null || $score > $best ? $score : $best;
                    }
                }

                return $best;
            }

            /**
             * @return list<SearchTerm>
             */
            public function terms(): array
            {
                return $this->text === '' ? $this->alternatives : [new SearchTerm($this->text), ...$this->alternatives];
            }

            /**
             * @return list<SearchFilter>
             */
            public function filtersOf(string $key): array
            {
                return array_values(array_filter($this->filters, static fn (SearchFilter $filter): bool => $filter->key === $key));
            }

            public function hasFilters(): bool
            {
                return $this->filters !== [];
            }
        }
    }
}
