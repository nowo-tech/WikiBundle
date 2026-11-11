<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Service;

use Stringable;
use Symfony\Contracts\Translation\TranslatableInterface;

use function interface_exists;

/*
 * 1.30: the BreadcrumbKit (>= 2.17) port the optional integration of web_ui.breadcrumb_current_label calls, for the
 * tests and PHPStan only (BreadcrumbKit is not a dependency). The real interface wins when it is installed.
 */
if (!interface_exists(BreadcrumbCurrentLabelInterface::class)) {
    interface BreadcrumbCurrentLabelInterface
    {
        /**
         * @param array<string, mixed> $translationParameters
         */
        public function setCurrentLabel(string|Stringable|TranslatableInterface|null $label, ?string $translationDomain = null, array $translationParameters = []): void;

        public function clearCurrentLabel(): void;
    }
}
