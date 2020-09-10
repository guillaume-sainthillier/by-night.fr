<?php

declare(strict_types=1);

use Rector\Core\Configuration\Option;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('auto_import_names', true);
    $parameters->set('symfony_container_xml_path', __DIR__ . '/var/cache/dev/srcApp_KernelDevDebugContainer.xml');
    $parameters->set('paths', [__DIR__ . '/src']);

    $parameters->set(Option::SETS, [
        SetList::CODE_QUALITY,
        SetList::PHP_71,
        SetList::PHP_72,
        SetList::PHP_73,
        SetList::PHP_74,
    ]);

    $parameters->set('exclude_rectors', [
        RemoveExtraParametersRector::class
    ]);
};
