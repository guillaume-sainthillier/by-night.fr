<?php

namespace App\Tests\Utils;

use App\Entity\City;
use App\Entity\ZipCity;
use App\Handler\EventHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Entity\Place;
use App\Entity\Agenda;

class HandlerTest extends KernelTestCase
{
    /**
     * @var \App\Handler\EventHandler
     */
    protected $handler;

    public function setUp()
    {
        self::bootKernel();
        $this->handler = static::$kernel->getContainer()->get(EventHandler::class);
    }

    public function testHandleEvent()
    {
        $now = new \DateTime();

        $oclub      = (new Place())->setId(1)->setNom('Oclub')->setRue('101 Route d\'Agde')->setVille('Toulouse')->setCodePostal('31500');
        $oclubEvent = (new Agenda())->setId(1)->setNom('Super Event')->setDateDebut($now)->setDateFin($now)->setPlace($oclub);

        //Evenement à des lieux différents -> nouvel événément
        $opium      = (new Place())->setNom('Opium Club')->setRue('20 Rue Denfert Rochereau')->setVille('Toulouse')->setCodePostal('31000');
        $opiumEvent = (new Agenda())->setNom('Super Event')->setDateDebut($now)->setDateFin($now)->setPlace($opium);
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
        $tomorrow->modify('+1 day');
        $oclub      = (new Place())->setId(1)->setNom('Oclub')->setRue('101 Route d\'Agde')->setVille('Toulouse')->setCodePostal('31500');
        $opium      = (new Place())->setNom('Opium Club')->setRue('20 Rue Denfert Rochereau')->setVille('Toulouse')->setCodePostal('31000');
        $fbEvent    = $oclubEvent    = (new Agenda())->setId(1)->setNom('Super Event')->setDateDebut($now)->setDateFin($now)->setPlace($oclub)->setFacebookEventId(1);
        $newFbEvent = $oclubEvent = (new Agenda())->setNom('Mon Mega Event')->setDateDebut($tomorrow)->setDateFin($tomorrow)->setPlace($opium)->setFacebookEventId(1);
        $newFbEvent = $this->handler->handleEvent([$fbEvent], $newFbEvent);
        $this->assertEquals($newFbEvent->getId(), 1);
        $this->assertEquals($newFbEvent->getPlace()->getId(), null);
        $this->assertEquals($newFbEvent->getPlace()->getNom(), 'Opium Club');
        $this->assertEquals($opium->getNom(), 'Opium Club');
        $this->assertEquals($oclub->getNom(), 'Oclub');
    }

    public function testHandlePlace()
    {
        $toulouseZip = new ZipCity();
        $toulouseZip->setId(1)->setName('Toulouse')->setPostalCode('31000');

        $toulouseZip2 = new ZipCity();
        $toulouseZip2->setId(2)->setName('Toulouse')->setPostalCode('31500');

        $toulouse = new City();
        $toulouse->setName('Toulouse');

        //Construction des places
        $dynamo          = (new Place())->setId(1)->setNom('Dynamo')->setRue('6 rue Amélie')->setZipCity(clone $toulouseZip)->setCity(clone $toulouse);
        $bikini          = (new Place())->setId(2)->setNom('Le bikini')->setZipCity(clone $toulouseZip)->setCity(clone $toulouse);
        $moloko          = (new Place())->setId(3)->setNom('Moloko')->setRue('6 Rue Joutx Aigues')->setZipCity(clone $toulouseZip)->setCity(clone $toulouse);
        $persistedPlaces = [$dynamo, $bikini, $moloko];

        $place = (new Place())->setNom('Dynamo')->setRue('6 rue Amelie')->setZipCity(clone $toulouseZip)->setCity(clone $toulouse);
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertNotEquals($place, null);
        $this->assertEquals($place->getId(), 1);

        $place = (new Place())->setNom('Autre Lieu')->setRue('rue Amélie')->setZipCity(clone $toulouseZip)->setCity(clone $toulouse);
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertNotEquals($place, null);
        $this->assertEquals($place->getId(), null);

        $place = (new Place())->setNom('Bikini, Toulouse')->setCity(clone $toulouse);
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertNotEquals($place, null);
        $this->assertEquals($place->getId(), 2);

        $place = (new Place())->setNom('La gouaille')->setRue('6 Rue Joutx Aigues')->setZipCity(clone $toulouseZip)->setCity(clone $toulouse);
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertEquals($place->getId(), null);

        $place = (new Place())->setNom('Moloko')->setRue('6 Rue Joutx Aigues')->setZipCity(clone $toulouseZip2)->setCity(clone $toulouse);
        $place = $this->handler->handlePlace($persistedPlaces, $place);
        $this->assertEquals($place->getId(), 3);
    }
}
