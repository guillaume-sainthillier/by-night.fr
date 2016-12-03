<?php

namespace TBN\MajDataBundle\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\ConsoleOutput;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MajDataBundle\Utils\Merger;
use TBN\MajDataBundle\Utils\Monitor;

class MergerTest extends KernelTestCase
{
    /**
     * @var Merger $merger
     */
    protected $merger;

    public function setUp()
    {
        self::bootKernel();

        $this->merger = static::$kernel->getContainer()->get('tbn.merger');
        Monitor::$output = new ConsoleOutput();
    }


    protected function tearDown()
    {
        Monitor::writeln("Merger");
        Monitor::displayStats();
        parent::tearDown();
    }

    public function testPlaceMerge() {
        $oldPlace = (new Place)->setId(1)->setNom('Dynamo')->setVille('Toulouse')->setCodePostal('31000');

        $newPlace = (new Place)->setNom('La Dynamo')->setVille('Toulouse')->setCodePostal('31000')->setLatitude(43.6);

        $this->merger->mergePlace($oldPlace, $newPlace);

        $this->assertEquals($oldPlace->getId(), 1);
        $this->assertEquals($oldPlace->getNom(), "La Dynamo");
        $this->assertEquals($oldPlace->getLatitude(), 43.6);
    }

    public function testSimpleMerge()
    {
        $oldEvent = (new Agenda)
            ->setId(1)
            ->setNom("Lorem Ipsum")
            ->setDateDebut(\Datetime::createFromFormat("Y-m-d", "2016-29-11"))
        ;

        $newEvent = (new Agenda)
            ->setNom("New Lorem Ipsum")
            ->setDateDebut(\Datetime::createFromFormat("Y-m-d", "2016-29-11"))
        ;

        $this->merger->mergeEvent($oldEvent, $newEvent);

        $this->assertEquals($oldEvent->getId(), 1);
        $this->assertEquals($oldEvent->getNom(), $newEvent->getNom());
        $this->assertEquals($oldEvent->getDateDebut(), $newEvent->getDateDebut());

        /*
        //Construction des places
        $dynamo = (new Place)->setId(1)->setNom('Dynamo')->setVille('Toulouse')->setCodePostal('31000')->setSite($site);
        $bikini = (new Place)->setId(2)->setNom('Le bikini')->setVille('Toulouse')->setCodePostal('31000')->setSite($site);
        $persistedPlaces = [$dynamo, $bikini];


        //Construction des événements
        $now = new \DateTime;
        $soiree1 = (new Agenda)->setId(1)->setNom('Soirée chez moi')->setDescriptif('Ca va être trop cool on va tous se marrer')->setDateFin($now)->setDateDebut($now)->setPlace($dynamo);

        $dynamoBis = (new Place)->setNom('La dynamo')->setRue('6 rue Amélie')->setVille('Toulouse')->setCodePostal('31000');
        $soiree = (new Agenda)->setNom('Soirée chez toi')->setDescriptif('Ca va être trop cool on va tous se marrer')->setDateFin($now)->setDateDebut($now)->setPlace($dynamoBis);
        $handler->handle($persistedPlaces, $site, $soiree);
        $this->assertEquals($soiree->getPlace()->getId(), 1);
        $this->assertEquals($soiree->getPlace()->getRue(), '6 Rue Amélie'); //Rue mise à jour
        */
    }
}
