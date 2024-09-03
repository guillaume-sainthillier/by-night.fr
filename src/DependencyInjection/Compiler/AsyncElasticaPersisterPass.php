<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\DependencyInjection\Compiler;

use App\Elasticsearch\AsyncObjectPersister;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class AsyncElasticaPersisterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds('fos_elastica.persister')) as $id) {
            $service = $container->getDefinition($id);
            $newServiceId = str_replace('fos_elastica.', 'app.', $id);
            $decorator = new Definition($newServiceId);
            $decorator
                ->setClass(AsyncObjectPersister::class)
                ->setDecoratedService($id)
                ->setArgument(0, new Reference(\sprintf('%s.inner', $newServiceId)))
                // index
                ->setArgument(1, $service->getArgument(0))
                // options
                ->setArgument(2, $service->getArgument(4))
                ->setAutowired(true)
            ;

            $container->setDefinition($newServiceId, $decorator);
        }
    }
}
