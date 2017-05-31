<?php

namespace Tests\AppBundle\Utils;

use AppBundle\Entity\City;
use AppBundle\Entity\Country;
use AppBundle\Entity\ZipCity;
use AppBundle\Reject\Reject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


use AppBundle\Entity\Place;
use AppBundle\Entity\Agenda;


class DoctrineHandlerTest extends KernelTestCase
{

    /**
     * @var \AppBundle\Handler\DoctrineEventHandler
     */
    protected $doctrineHandler;

    public function setUp()
    {
        self::bootKernel();
        $this->doctrineHandler = static::$kernel->getContainer()->get('tbn.doctrine_event_handler');
    }

    public function testGuessEventLocation()
    {
        $reject = new Reject();
        $place = (new Place())->setNom('L\'envol Côté Plage')->setCodePostal('31130')->setVille('Toulouse')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertEquals($place->getZipCity(), null); //31130 n'est pas un code postal de Toulouse, il ne doit donc pas y avoir de zipCity
        $this->assertNotEquals($place->getCity(), null); //La ville de Toulouse doit être trouvée grace au nom unique de Toulouse en France
        $this->assertNotEquals($place->getCountry(), null); //La place doit avoir un pays
        $this->assertEquals($place->getCountry()->getId(), "FR"); //Ce pays doit être la France
        $this->assertTrue($place->getReject()->isValid()); //La place doit être valide car une ville a été trouvée


        $reject = new Reject();
        $place = (new Place())->setNom('L\'envol Côté Plage')->setVille('Toulouse')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertEquals($place->getZipCity(), null); //La place n'a pas de code postal, il ne doit donc pas y avoir de zipCity
        $this->assertNotEquals($place->getCity(), null); //La ville de Toulouse doit être trouvée grace au nom unique de Toulouse en France
        $this->assertNotEquals($place->getCountry(), null); //La place doit avoir un pays
        $this->assertEquals($place->getCountry()->getId(), "FR"); //Ce pays doit être la France
        $this->assertTrue($place->getReject()->isValid()); //La place doit être valide car une ville a été trouvée


        $reject = new Reject();
        $place = (new Place())->setNom('Mon super événement')->setLongitude(1.5015516539334)->setLatitude(43.58622409873)->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertNotEquals($place->getZipCity(), null); //La ZipCity doit être trouvée
        $this->assertEquals($place->getZipCity()->getName(), "Balma");
        $this->assertEquals($place->getZipCity()->getPostalCode(), "31130");
        $this->assertNotEquals($place->getCity(), null); //Une ville doit être trouvée grace à la géoloc
        $this->assertEquals($place->getCity()->getName(), "Balma"); //Une ville doit être trouvée grace à la géoloc
        $this->assertNotEquals($place->getCountry(), null); //La place doit avoir un pays
        $this->assertEquals($place->getCountry()->getId(), "FR"); //Ce pays doit être la France
        $this->assertTrue($place->getReject()->isValid()); //La place doit être valide car une ville a été trouvée


        $reject = new Reject();
        $place = (new Place())->setNom('10, Av Princesse Grace')->setLongitude(7.4314023071828)->setLatitude(43.743460394373)->setReject($reject);
        $this->doctrineHandler->guessEventLocation($place);
        dump($place);
    }
}
