<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Debug\Debug;

require __DIR__ . '/../vendor/autoload.php';

if (getenv('APP_DEBUG')) {

    // Deny if client address is remote and is not in a container
    if (!in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true) && false === getenv('SYMFONY_ALLOW_APPDEV')) {
        header('HTTP/1.0 403 Forbidden');
        exit('You are not allowed to access this file. Check ' . basename(__FILE__) . ' for more information.');
    }

    Debug::enable();
}

$kernel = new AppKernel(getenv('APP_ENV'), getenv('APP_DEBUG'));
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
