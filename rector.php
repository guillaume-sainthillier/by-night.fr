<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\ArrayThisCallToThisMethodCallRector;
use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\Core\Configuration\Option;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;
use Rector\Set\ValueObject\SetList;
use Rector\SOLID\Rector\ClassMethod\UseInterfaceOverImplementationInConstructorRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::AUTO_IMPORT_NAMES, true);
    $parameters->set(Option::SYMFONY_CONTAINER_XML_PATH_PARAMETER, __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');

    $parameters->set(Option::AUTOLOAD_PATHS, [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/bin/.phpunit/phpunit/vendor/autoload.php',
    ]);


    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $parameters->set(Option::SETS, [
        SetList::CODE_QUALITY,
        SetList::PHP_70,
        SetList::PHP_71,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
        SetList::SYMFONY_40,
        SetList::SYMFONY_41,
        SetList::SYMFONY_42,
        SetList::SYMFONY_43,
        SetList::SYMFONY_44,
        SetList::SYMFONY_50,
        SetList::SYMFONY_50_TYPES,
    ]);

    //"Syntax error, unexpected T_MATCH:136".
    $parameters->set(Option::EXCLUDE_PATHS, [
        __DIR__ . '/src/SearchRepository/EventElasticaRepository.php',
    ]);

    $parameters->set(Option::EXCLUDE_RECTORS, [
        CallableThisArrayToAnonymousFunctionRector::class,
        ArrayThisCallToThisMethodCallRector::class,
        RemoveExtraParametersRector::class,
        ListToArrayDestructRector::class,
    ]);

    $parameters->set('skip', [
        UseInterfaceOverImplementationInConstructorRector::class => [
            __DIR__ . '/src/Entity',
        ]
    ]);

    $parameters->set(Option::ENABLE_CACHE, true);
};
