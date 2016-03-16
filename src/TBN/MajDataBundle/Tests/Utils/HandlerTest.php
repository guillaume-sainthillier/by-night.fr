<?php

namespace TBN\MajDataBundle\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Utils\EventHandler;
use TBN\MajDataBundle\Utils\Monitor;

class HandlerTest extends KernelTestCase
{

    protected $container;
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    public function setUp()
    {
        self::bootKernel();
        $this->container = static::$kernel->getContainer();
        $this->em = static::$kernel->getContainer()
            ->get('doctrine')
            ->getManager();
        Monitor::$output = new ConsoleOutput();
    }


    protected function tearDown()
    {
        $this->em->close();
        parent::tearDown();
    }

    public function testHandle()
    {
        $handler = $this->container->get('tbn.event_handler');

        $site = (new Site)
            ->setNom('Toulouse')
            ->setLatitude(43.6)
            ->setLongitude(1.43333)
            ->setDistanceMax(0.2);

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

        Monitor::displayStats();
    }

    public function testEvents()
    {
        $site = (new Site)
            ->setNom('Toulouse')
            ->setLatitude(43.296482)
            ->setLongitude(5.36978)
            ->setDistanceMax(0.4);

        $handler = $this->container->get('tbn.event_handler');

        //Construction des places
        $dynamo = (new Place)->setId(1)->setNom('Dynamo')->setRue('6 rue Amélie')->setVille('Toulouse')->setCodePostal('31000')->setSite($site);
        $bikini = (new Place)->setId(2)->setNom('Le bikini')->setVille('Toulouse')->setCodePostal('31500')->setSite($site);

        //Construction des événements
        $now = new \DateTime;
        $soiree1 = (new Agenda)->setId(1)->setNom('Soirée chez moi')->setDescriptif('Ca va être trop cool on va tous se marrer')->setDateFin($now)->setDateDebut($now)->setPlace($dynamo)->setSite($site);
        $persistedEvents = [$soiree1];

        //Tests
        $place = (new Place)->setNom('A la dynamo')->setVille('Toulouse')->setCodePostal('31500')->setSite($site);
        $soiree = (new Agenda)->setNom('Soiree ché moi')->setDescriptif('Ca va être trop cool on va tous se marrer')->setDateFin($now)->setDateDebut($now)->setPlace($place)->setSite($site);
        $bestEvent = $handler->handleEvent($persistedEvents, $soiree);


        $this->assertEquals($bestEvent->getId(), 1); //Event récupérée dans les persisted
        $this->assertEquals($bestEvent->getPlace()->getId(), 1); //Place récupérée dans les persisted
        $this->assertEquals($bestEvent->getPlace()->getVille(), 'Toulouse'); //Ville récupérée dans les persisted
        $this->assertEquals($bestEvent->getPlace()->getCodePostal(), '31000'); //Ville récupérée dans les persisted

        $place = (new Place)->setNom('Esplanade Du J4')->setLatitude(43.298522)->setLongitude(5.362259)->setSite($site);
        $soiree = (new Agenda)->setNom('La 36ème Traversée du Vieux Port')->setDescriptif('Ca va être trop cool on va tous se marrer')->setTrustedLocation(false)->setDateFin($now)->setPlace($place)->setSite($site);
        $bestEvent = $handler->handleEvent([], $soiree);
        $this->assertNotEquals($bestEvent, null); //Event validé par le firewall
    }


    public function testPlaces()
    {
        $site = (new Site)
            ->setNom('Toulouse')
            ->setLatitude(43.6)
            ->setLongitude(1.43333)
            ->setDistanceMax(0.2);

        /**
         * @var EventHandler $handler
         */
        $handler = $this->container->get('tbn.event_handler');

        //Construction des places
        $dynamo = (new Place)->setId(1)->setNom('Dynamo')->setRue('6 rue Amélie')->setVille('Toulouse')->setCodePostal('31000')->setSite($site);
        $bikini = (new Place)->setId(2)->setNom('Le bikini')->setVille('Toulouse')->setCodePostal('31000')->setSite($site);
        $persistedPlaces = [$dynamo, $bikini];

        $dynamoBis = (new Place)->setNom('La dynamo')->setVille('Toulouse')->setCodePostal('31000')->setSite($site);
        $bestDynamo = $handler->handlePlace($persistedPlaces, $dynamoBis);
        $this->assertEquals($bestDynamo->getId(), 1); //Place récupérée dans les persisted        

        $bikiniBis = (new Place)->setNom('Bikini')->setRue('36 rue chépaoù')->setVille('Toulouse')->setCodePostal('31000')->setSite($site);
        $bestBikini = $handler->handlePlace($persistedPlaces, $bikiniBis);
        $this->assertEquals($bestBikini->getId(), 2); //Place récupérée dans les persisted
        $this->assertEquals($bestBikini->getRue(), '36 rue chépaoù'); //Rue mise à jour
    }
}
