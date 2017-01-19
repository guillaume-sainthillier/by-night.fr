<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Composer\Autoload\ClassLoader;
/**
 * @var ClassLoader $loader
 */
$loader = require __DIR__.'/../vendor/autoload.php';
$loader->add('Google',__DIR__.'/../vendor/dsyph3r/google-geolocation');

AnnotationRegistry::registerLoader(array($loader, 'loadClass'));
return $loader;
