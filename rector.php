<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Rector\MethodCall\ContainerGetToConstructorInjectionRector;
use Rector\Symfony\Set\SymfonyLevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/migrations',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // define sets of rules
    $rectorConfig->sets([
        SymfonyLevelSetList::UP_TO_SYMFONY_54,
        LevelSetList::UP_TO_PHP_80,
    ]);

    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/Entity/*',
        ],
        ContainerGetToConstructorInjectionRector::class => [
            __DIR__ . '/migrations',
        ],
    ]);
};
