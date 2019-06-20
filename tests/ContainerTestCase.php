<?php


namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Dotenv\Dotenv;

abstract class ContainerTestCase extends KernelTestCase
{
    protected function setUp()
    {
        (new Dotenv())->load(__DIR__ . '/../.env.test');
        static::bootKernel();
    }
}
