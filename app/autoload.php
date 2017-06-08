<?php

use Composer\Autoload\ClassLoader;
use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * @var ClassLoader
 */
$loader = require __DIR__.'/../vendor/autoload.php';
$loader->add('Google', __DIR__.'/../vendor/dsyph3r/google-geolocation');

AnnotationRegistry::registerLoader([$loader, 'loadClass']);

return $loader;
