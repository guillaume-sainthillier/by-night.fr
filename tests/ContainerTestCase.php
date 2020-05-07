<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Dotenv\Dotenv;

abstract class ContainerTestCase extends KernelTestCase
{
    protected function setUp(): void
    {
        (new Dotenv(true))->load(__DIR__ . '/../.env.test');
        static::bootKernel();
    }
}
