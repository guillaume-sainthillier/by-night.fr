<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Rector\CodeQuality\Rector\If_\ExplicitNullableObjectInstanceRector;
use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Property\RemoveDefaultValueFromAssignedPropertyRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    ->withImportNames(removeUnusedImports: true)
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
    ->withCache(__DIR__ . '/var/tools/rector')
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // uncomment to reach your current PHP version
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true
    )
    ->withComposerBased()
    ->withSets([
        Zenstruck\Foundry\Utils\Rector\FoundrySetList::FOUNDRY_2_7,
        Zenstruck\Foundry\Utils\Rector\FoundrySetList::FOUNDRY_2_9,
    ])
    ->withSkip([
        ExplicitNullableObjectInstanceRector::class,
        Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector::class,
        CatchExceptionNameMatchingTypeRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/Entity/ResetPasswordRequest.php',
            // A message queued before $pageEventIds existed unserializes with the property default, which a promoted property has not
            __DIR__ . '/src/Message/DownloadEventImages.php',
        ],
        // Same: the default of $pageEventIds is what an older queued message unserializes with
        RemoveDefaultValueFromAssignedPropertyRector::class => [
            __DIR__ . '/src/Message/DownloadEventImages.php',
        ],
    ])
    ->reportUnusedSkips()
;
