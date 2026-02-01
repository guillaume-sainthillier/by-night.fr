<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\DependencyInjection\Compiler;

use App\Contracts\BatchResetInterface;
use App\Manager\BatchResetManager;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;

/**
 * @see ResettableServicePass
 */
final class BatchResetCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $services = [];
        foreach (array_keys($container->findTaggedServiceIds(BatchResetInterface::class, true)) as $id) {
            // We don't want to instantiate services which were not instantiated yet in order to just reset them
            $services[$id] = new Reference($id, ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE);
        }

        $container
            ->findDefinition(BatchResetManager::class)
            ->setArgument(0, new IteratorArgument($services))
        ;
    }
}
