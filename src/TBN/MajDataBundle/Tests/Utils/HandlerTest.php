<?php

namespace TBN\MajDataBundle\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Entity\Site;
use TBN\MajDataBundle\Handler\EventHandler;
use TBN\MajDataBundle\Utils\Merger;
use TBN\MajDataBundle\Utils\Monitor;

class HandlerTest extends KernelTestCase
{

    /**
     * @var \TBN\MajDataBundle\Handler\EventHandler
     */
    protected $handler;

    public function setUp()
    {
        self::bootKernel();
        $this->handler = static::$kernel->getContainer()->get('tbn.event_handler');
    }

    public function testHandleEvent() {
        $now = new \DateTime;

        $oclub = (new Place)->setId(1)->setNom('Oclub')->setRue('101 Route d\'Agde')->setVille('Toulouse')->setCodePostal('31500');
        $oclubEvent = (new Agenda)->setId(1)->setNom('Super Event')->setDateDebut($now)->setDateFin($now)->setPlace($oclub);

        //Evenement à des lieux différents -> nouvel événément
        $opium = (new Place)->setNom('Opium Club')->setRue('20 Rue Denfert Rochereau')->setVille('Toulouse')->setCodePostal('31000');
        $opiumEvent = (new Agenda)->setNom('Super Event')->setDateDebut($now)->setDateFin($now)->setPlace($opium);
        $opiumEvent = $this->handler->handleEvent([$oclubEvent], $opiumEvent);
        $this->assertEquals($opiumEvent->getId(), null);
        $this->assertEquals($opiumEvent->getPlace()->getId(), null);
        $this->assertEquals($oclubEvent->getPlace()->getNom(), 'Oclub');

        //Mêmes événéments -> pas de nouvel événément
        $newOclubEvent = clone $oclubEvent;
        $newOclubEvent->setId(null)->setNom('Mon Super Event');
        $newOclubEvent = $this->handler->handleEvent([$oclubEvent], $newOclubEvent);
        $this->assertEquals($newOclubEvent->getId(), 1);
        $this->assertEquals($newOclubEvent->getPlace()->getId(), 1);
        $this->assertEquals($newOclubEvent->getPlace()->getNom(), 'Oclub');

        //Mêmes événéments FB -> pas de nouvel événément
        $tomorrow = clone $now;
        $tomorrow->modify("+1 day");
        $oclub = (new Place)->setId(1)->setNom('Oclub')->setRue('101 Route d\'Agde')->setVille('Toulouse')->setCodePostal('31500');
        $opium = (new Place)->setNom('Opium Club')->setRue('20 Rue Denfert Rochereau')->setVille('Toulouse')->setCodePostal('31000');
        $fbEvent = $oclubEvent = (new Agenda)->setId(1)->setNom('Super Event')->setDateDebut($now)->setDateFin($now)->setPlace($oclub)->setFacebookEventId(1);
        $newFbEvent = $oclubEvent = (new Agenda)->setNom('Mon Mega Event')->setDateDebut($tomorrow)->setDateFin($tomorrow)->setPlace($opium)->setFacebookEventId(1);
        $newFbEvent = $this->handler->handleEvent([$fbEvent], $newFbEvent);
        $this->assertEquals($newFbEvent->getId(), 1);
        $this->assertEquals($newFbEvent->getPlace()->getId(), null);
        $this->assertEquals($newFbEvent->getPlace()->getNom(), 'Opium Club');
        $this->assertEquals($opium->getNom(), 'Opium Club');
        $this->assertEquals($oclub->getNom(), 'Oclub');
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

        $place = (new Place)->setNom('Moloko')->setRue('6 Rue Joutx Aigues')->setVille('Toulouse')->setCodePostal('31500');
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertEquals($place->getId(), 3);
    }
}
