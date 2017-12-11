<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 10/12/2017
 * Time: 15:15.
 */

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Dotenv\Dotenv;

abstract class ContainerTestCase extends KernelTestCase
{
    protected function setUp()
    {
        (new Dotenv())->load(__DIR__.'/../.env');
        static::bootKernel();
    }
}
