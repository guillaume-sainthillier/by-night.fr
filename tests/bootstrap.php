<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Dotenv\Dotenv;

require \dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(\dirname(__DIR__) . '/config/bootstrap.php')) {
    require \dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(\dirname(__DIR__) . '/.env');
}
