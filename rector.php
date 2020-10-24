<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\ArrayThisCallToThisMethodCallRector;
use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\Core\Configuration\Option;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('auto_import_names', true);
    $parameters->set('symfony_container_xml_path', __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');
    $parameters->set('paths', [
        __DIR__ . '/src',
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

    $parameters->set('exclude_rectors', [
        CallableThisArrayToAnonymousFunctionRector::class,
        ArrayThisCallToThisMethodCallRector::class,
        RemoveExtraParametersRector::class,
        ListToArrayDestructRector::class,
    ]);

    $parameters->set(Option::ENABLE_CACHE, true);
};
