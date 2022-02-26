<?php

declare(strict_types=1);

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Rector\Core\Configuration\Option;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Rector\MethodCall\ContainerGetToConstructorInjectionRector;
use Rector\Symfony\Rector\MethodCall\SimplifyWebTestCaseAssertionsRector;
use Rector\Symfony\Set\SymfonyLevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    // get parameters
    $parameters = $containerConfigurator->parameters();

    $parameters
        ->set(Option::IMPORT_DOC_BLOCKS, true)
        ->set(Option::AUTO_IMPORT_NAMES, true)
        ->set(Option::PATHS, [
            __DIR__ . '/src',
            __DIR__ . '/migrations',
        ])
        ->set(Option::AUTOLOAD_PATHS, [
            __DIR__ . '/vendor/autoload.php',
        ])
        ->set(Option::SYMFONY_CONTAINER_XML_PATH_PARAMETER, __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml')
        ->set(Option::SKIP, [
            SimplifyWebTestCaseAssertionsRector::class,
            ClassPropertyAssignToConstructorPromotionRector::class => [
                __DIR__ . '/src/Entity/*',
                __DIR__ . '/src/*/Entity/*',
            ],
            ContainerGetToConstructorInjectionRector::class => [
                __DIR__ . '/migrations',
            ],
        ]);

    $containerConfigurator->import(LevelSetList::UP_TO_PHP_80);
    $containerConfigurator->import(SymfonySetList::SYMFONY_CODE_QUALITY);
    $containerConfigurator->import(SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION);
    $containerConfigurator->import(SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES);
    $containerConfigurator->import(DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES);
    $containerConfigurator->import(SymfonyLevelSetList::UP_TO_SYMFONY_54);

    $services = $containerConfigurator->services();
    $services->set(AnnotationToAttributeRector::class)->configure([
        new AnnotationToAttribute('Vich\\UploaderBundle\\Mapping\\Annotation\\Uploadable'),
        new AnnotationToAttribute('Vich\\UploaderBundle\\Mapping\\Annotation\\UploadableField'),
        new AnnotationToAttribute('JMS\\Serializer\\Annotation\\ExclusionPolicy'),
        new AnnotationToAttribute('JMS\\Serializer\\Annotation\\Groups'),
        new AnnotationToAttribute('JMS\\Serializer\\Annotation\\Expose'),
        new AnnotationToAttribute('JMS\\Serializer\\Annotation\\Exclude'),
        new AnnotationToAttribute('Gedmo\\Mapping\\Annotation\\Slug'),
        new AnnotationToAttribute('Gedmo\\Mapping\\Annotation\\Timestampable'),
    ]);
};
