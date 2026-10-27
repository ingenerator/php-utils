<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__.'/test',
    ])
    ->withRootFiles()
    ->withPreparedSets(
        codeQuality: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        phpunitCodeQuality: true
    )
    ->withPhpSets(php82: true)
    ->withSkip([
        // Don't make whole classes read-only in libraries, it makes BC awkward
        ReadOnlyClassRector::class,
        // Don't force test properties to readonly
        ReadOnlyPropertyRector::class => [
            __DIR__.'/test/unit/Object/ObjectPropertyPopulatorTest.php',
        ],
    ])
    ->withImportNames(
        removeUnusedImports: true,
    )
;
