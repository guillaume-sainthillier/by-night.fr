<?php

use Symfony\Component\ClassLoader\XcacheClassLoader;
use Symfony\Component\HttpFoundation\Request;

$loader = require_once __DIR__.'/../app/bootstrap.php.cache';

$XCacheLoader = new XcacheClassLoader(sha1(__FILE__), $loader);
$loader->unregister();
$XCacheLoader->register(true);


//require_once __DIR__.'/../app/AppKernel.php';
require_once __DIR__.'/../app/AppCache.php';

$appKernel = new AppKernel('prod', false);
$appKernel->loadClassCache();
$kernel = new AppCache($appKernel);

Request::enableHttpMethodParameterOverride();
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
