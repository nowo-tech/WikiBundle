<?php

declare(strict_types=1);

use Rector\CodingStyle\Rector\ArrowFunction\ArrowFunctionDelegatingCallToFirstClassCallableRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSkip([
        __DIR__ . '/demo',
        // Public API: promoting $securityUser renames the public constructor parameter $user, which breaks every host
        // that builds these objects with named arguments (`user: ...`); the deprecated `->user` member goes in 2.0.
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/Api/WikiApiPrincipal.php',
            __DIR__ . '/src/Event/WikiEditorOptionsEvent.php',
            __DIR__ . '/src/Event/WikiQuotaCheckEvent.php',
        ],
        // The arrow function is re-bound with Closure::call() to reach a private method of the service under test;
        // `$this->quickAccessExport(...)` would be resolved on the test case itself (undefined method).
        ArrowFunctionDelegatingCallToFirstClassCallableRector::class => [
            __DIR__ . '/tests/Unit/Command/WikiCommentsEraseUserQuickAccessTest.php',
        ],
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
