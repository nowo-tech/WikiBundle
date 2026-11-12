<?php

declare(strict_types=1);

/*
 * 1.31: the BreadcrumbKit (>= 2.17) trail DTOs and event (2.17.0 signatures) the optional parent-crumb label of
 * web_ui.breadcrumb_current_label reads, for the tests and PHPStan only (BreadcrumbKit is not a dependency). The real
 * classes win when they are installed.
 */

namespace Nowo\BreadcrumbKitBundle\Dto {
    use function class_exists;

    if (!class_exists(BreadcrumbCollapse::class)) {
        final readonly class BreadcrumbCollapse
        {
            public function __construct(
                public bool $enabled = false,
                public int $minItems = 3,
                public string $breakpoint = '40rem',
            ) {
            }
        }
    }

    if (!class_exists(BreadcrumbNode::class)) {
        final readonly class BreadcrumbNode
        {
            /**
             * @param array<string, scalar|null> $routeParams
             */
            public function __construct(
                public string $label,
                public ?string $url,
                public bool $linkEnabled,
                public bool $current,
                public ?string $icon = null,
                public array $routeParams = [],
            ) {
            }
        }
    }

    if (!class_exists(BreadcrumbTrailView::class)) {
        final readonly class BreadcrumbTrailView
        {
            /**
             * @param list<BreadcrumbNode> $nodes
             * @param array<string, mixed> $responsiveConfig
             */
            public function __construct(
                public array $nodes,
                public ?string $homeIcon = null,
                public ?string $separatorIcon = null,
                public ?string $classList = null,
                public ?string $classItem = null,
                public ?string $classSeparator = null,
                public ?string $classCurrent = null,
                public array $responsiveConfig = [],
                public bool $homeIconReplacesLabel = true,
                public BreadcrumbCollapse $collapse = new BreadcrumbCollapse(),
            ) {
            }
        }
    }
}

namespace Nowo\BreadcrumbKitBundle\Event {
    use Nowo\BreadcrumbKitBundle\Dto\BreadcrumbTrailView;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Contracts\EventDispatcher\Event;

    use function class_exists;

    if (!class_exists(BreadcrumbTrailBuiltEvent::class)) {
        final class BreadcrumbTrailBuiltEvent extends Event
        {
            public readonly ?string $routeName;

            /** @var array<string, scalar|null> */
            public readonly array $routeParameters;

            public function __construct(
                private BreadcrumbTrailView $view,
                public readonly string $collectionCode,
                public readonly string $contextKey,
                public readonly string $status,
                public readonly ?Request $request,
                public readonly ?string $matchedItemRoute,
            ) {
                $route           = $request?->attributes->get('_route');
                $this->routeName = \is_string($route) ? $route : null;
                $parameters      = $request?->attributes->get('_route_params');
                $scalars         = [];
                foreach (\is_array($parameters) ? $parameters : [] as $name => $value) {
                    if (\is_string($name) && ($value === null || \is_scalar($value))) {
                        $scalars[$name] = $value;
                    }
                }
                $this->routeParameters = $scalars;
            }

            public function getView(): BreadcrumbTrailView
            {
                return $this->view;
            }

            public function setView(BreadcrumbTrailView $view): void
            {
                $this->view = $view;
            }
        }
    }
}
