<?php

namespace TBN\MainBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class TBNCityExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
//        $loader->load('services.yml');

//        $this->addClassesToCompile([
//            'TBN\MainBundle\\Geolocalize',
//            'TBN\MainBundle\\AgendaRepository',
//            'TBN\MainBundle\\Listener',
//            'TBN\MainBundle\\Routing',
//            'TBN\MainBundle\\Site',
//            'TBN\MainBundle\\Twig',
//            'TBN\MainBundle\\Cleaner',
//            'TBN\MainBundle\\Fetcher',
//            'TBN\MainBundle\\Reject',
//            'TBN\MainBundle\\Utils',
//            'TBN\MainBundle\\Exception',
//            'TBN\MainBundle\\Captcha',
//            'TBN\MainBundle\\EventListener',
//            'TBN\MainBundle\\Handler',
//            'TBN\MainBundle\\Validator\\Constraints',
//            'TBN\MainBundle\\Social',
//            'TBN\MainBundle\\Repository',
//            'TBN\MainBundle\\Parser',
//        ]);
//
//        $this->addAnnotatedClassesToCompile([
//            'TBN\MainBundle\\Search',
//        ]);
    }
}
