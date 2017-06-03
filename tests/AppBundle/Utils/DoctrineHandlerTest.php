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
        //Mauvais CP + mauvaise ville + mauvais pays
        $reject = new Reject();
        $place = (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setReject($reject)->setCountryName('LoremIpsum');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), null);
        $this->assertCountry($place->getCountry(), null);
        $this->assertEquals($place->getReject()->getReason(), Reject::BAD_COUNTRY | Reject::VALID);

        //Mauvais CP + mauvaise ville + bon pays
        $reject = new Reject();
        $place = (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), null);
        $this->assertCountry($place->getCountry(), "FR");
        $this->assertEquals($place->getReject()->getReason(), Reject::BAD_PLACE_CITY_NAME | Reject::VALID);

        //Mauvais CP + bonne ville + bon pays
        $reject = new Reject();
        $place = (new Place())->setCodePostal('99999')->setVille('St Germain En Laye')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), "Saint-Germain-en-Laye");
        $this->assertCountry($place->getCountry(), "FR");
        $this->assertTrue($place->getReject()->isValid());

        //Mauvais CP + ville doublon + bon pays
        $reject = new Reject();
        $place = (new Place())->setCodePostal('31000')->setVille('Roques')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), null);
        $this->assertCountry($place->getCountry(), "FR");
        $this->assertEquals($place->getReject()->getReason(), Reject::AMBIGOUS_CITY | Reject::VALID);

        //Pas de CP + bonne ville + bon pays
        $reject = new Reject();
        $place = (new Place())->setVille('Toulouse')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), 'Toulouse');
        $this->assertCountry($place->getCountry(), "FR");
        $this->assertTrue($place->getReject()->isValid());

        //Bonnes coordonnées + mauyvais pays
        $reject = new Reject();
        $place = (new Place())->setNom('10, Av Princesse Grace')->setLongitude(7.4314023071828)->setLatitude(43.743460394373)->setReject($reject)->setCountryName('France')
            ->setCountry((new Country())->setId('FR'))
        ;
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), "98000", "Monaco");
        $this->assertCity($place->getCity(), "Monaco");
        $this->assertCountry($place->getCountry(), 'MC');
        $this->assertTrue($place->getReject()->isValid());
    }

    private function assertCity(City $city = null, $name = null) {
        if($name === null) {
            $this->assertEquals($city, null);
        }else {
            $this->assertNotEquals($city, null);
            $this->assertEquals($name, $city->getName());
        }
    }

    private function assertZipCity(ZipCity $zipCity = null, $postalCode = null, $city = null) {
        if($postalCode === null && $city === null) {
            $this->assertEquals($zipCity, null);
        }else {
            $this->assertNotEquals($zipCity, null);
            $this->assertEquals($postalCode, $zipCity->getPostalCode());
            $this->assertEquals($city, $zipCity->getName());
        }
    }

    private function assertCountry(Country $country = null, $value = null) {
        if($value === null) {
            $this->assertEquals($country, null);
        }else {
            $this->assertNotEquals($country, null);
            $this->assertEquals($value, $country->getId());
        }
    }
}
