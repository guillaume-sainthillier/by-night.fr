<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/04/2016
 * Time: 19:45.
 */

namespace AppBundle\DependencyInjection\Compiler;

use AppBundle\Routing\Router;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class OverrideServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('router.default');
        $definition->setClass(Router::class);
    }
}
