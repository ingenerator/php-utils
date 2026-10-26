<?php

declare(strict_types=1);

use Ingenerator\RiskyRectorRules\PhpDocToStrictTypes\AddParamTypeFromPhpDocRector;
use Rector\Config\RectorConfig;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__.'/test',
    ])
    ->withRootFiles()
    ->withPreparedSets(codeQuality: true, typeDeclarations: true, typeDeclarationDocblocks: true)
    ->withPhpSets(php82: true)
    ->withRules([
        AddParamTypeFromPhpDocRector::class,
    ])
    ->withSkip([
        // Don't make whole classes read-only in libraries, it makes BC awkward
        ReadOnlyClassRector::class,
    ])
    ->withImportNames(
        removeUnusedImports: true,
    )
;
