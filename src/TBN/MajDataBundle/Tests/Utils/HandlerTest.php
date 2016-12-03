<?php

namespace TBN\MajDataBundle\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Utils\EventHandler;
use TBN\MajDataBundle\Utils\Merger;
use TBN\MajDataBundle\Utils\Monitor;

class HandlerTest extends KernelTestCase
{

    /**
     * @var EventHandler
     */
    protected $handler;

    public function setUp()
    {
        self::bootKernel();
        $this->handler = static::$kernel->getContainer()->get('tbn.event_handler');
        Monitor::$output = new ConsoleOutput();
    }

    protected function tearDown()
    {
        Monitor::writeln("Event Handler");
        Monitor::displayStats();
        parent::tearDown();
    }

    public function testHandlePlace()
    {
        //Construction des places
        $dynamo = (new Place)->setId(1)->setNom('Dynamo')->setRue('6 rue Amélie')->setVille('Toulouse')->setCodePostal('31000');
        $bikini = (new Place)->setId(2)->setNom('Le bikini')->setVille('Toulouse')->setCodePostal('31000');
        $moloko = (new Place)->setId(3)->setNom('Moloko')->setRue("6 Rue Joutx Aigues")->setVille('Toulouse')->setCodePostal('31000');
        $persistedPlaces = [$dynamo, $bikini, $moloko];

        $place = (new Place)->setNom('Dynamo')->setRue('6 rue Amélie')->setVille('Toulouse')->setCodePostal('31000');
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertNotEquals($place, null);
        $this->assertEquals($place->getId(), 1);

        $place = (new Place)->setNom('Autre Lieu')->setRue('rue Amélie')->setVille('Toulouse')->setCodePostal('31000');
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertNotEquals($place, null);
        $this->assertEquals($place->getId(), null);

        $place = (new Place)->setNom('Bikini')->setVille('Toulouse');
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertNotEquals($place, null);
        $this->assertEquals($place->getId(), 2);

        $place = (new Place)->setNom('La gouaille')->setRue('6 Rue Joutx Aigues')->setVille('Toulouse')->setCodePostal('31000');
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertEquals($place->getId(), null);
    }
}
