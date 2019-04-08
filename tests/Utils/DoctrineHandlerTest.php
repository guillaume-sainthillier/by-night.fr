<?php

namespace App\Tests\Utils;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\ZipCity;
use App\Handler\DoctrineEventHandler;
use App\Reject\Reject;
use App\Tests\ContainerTestCase;
use App\Entity\Place;

class DoctrineHandlerTest extends ContainerTestCase
{
    /**
     * @var \App\Handler\DoctrineEventHandler
     */
    protected $doctrineHandler;

    protected function setUp()
    {
        parent::setUp();

        $this->doctrineHandler = static::$container->get(DoctrineEventHandler::class);
    }

    public function testGuessEventLocation()
    {
        //Mauvais CP + mauvaise ville + mauvais pays
        $reject = new Reject();
        $place  = (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setReject($reject)->setCountryName('LoremIpsum');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), null);
        $this->assertCountry($place->getCountry(), null);
        $this->assertEquals($place->getReject()->getReason(), Reject::BAD_COUNTRY | Reject::VALID);

        //Mauvais CP + mauvaise ville + bon pays
        $reject = new Reject();
        $place  = (new Place())->setCodePostal('99999')->setVille('LoremIpsum')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), null);
        $this->assertCountry($place->getCountry(), 'FR');
        $this->assertEquals($place->getReject()->getReason(), Reject::BAD_PLACE_CITY_NAME | Reject::VALID);

        //Mauvais CP + bonne ville + bon pays
        $reject = new Reject();
        $place  = (new Place())->setCodePostal('99999')->setVille('St Germain En Laye')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), 'Saint-Germain-en-Laye');
        $this->assertCountry($place->getCountry(), 'FR');
        $this->assertTrue($place->getReject()->isValid());

        //Mauvais CP + ville doublon + bon pays
        $reject = new Reject();
        $place  = (new Place())->setCodePostal('31000')->setVille('Roques')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), null);
        $this->assertCountry($place->getCountry(), 'FR');
        $this->assertEquals($place->getReject()->getReason(), Reject::AMBIGOUS_CITY | Reject::VALID);

        //Pas de CP + bonne ville + bon pays
        $reject = new Reject();
        $place  = (new Place())->setVille('Toulouse')->setReject($reject)->setCountryName('France');
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), null, null);
        $this->assertCity($place->getCity(), 'Toulouse');
        $this->assertCountry($place->getCountry(), 'FR');
        $this->assertTrue($place->getReject()->isValid());

        //Bonnes coordonnées + mauvais pays
        $reject = new Reject();
        $place  = (new Place())->setNom('10, Av Princesse Grace')->setLongitude(7.4314023071828)->setLatitude(43.743460394373)->setReject($reject)->setCountryName('France')
            ->setCountry((new Country())->setId('FR')->setName('France'))
        ;
        $this->doctrineHandler->guessEventLocation($place);
        $this->assertZipCity($place->getZipCity(), '98000', 'Monaco');
        $this->assertCity($place->getCity(), 'Monaco');
        $this->assertCountry($place->getCountry(), 'MC');
        $this->assertTrue($place->getReject()->isValid());
    }

    private function assertCity(City $city = null, $name = null)
    {
        if (null === $name) {
            $this->assertEquals($city, null);
        } else {
            $this->assertNotEquals($city, null);
            $this->assertEquals($name, $city->getName());
        }
    }

    private function assertZipCity(ZipCity $zipCity = null, $postalCode = null, $city = null)
    {
        if (null === $postalCode && null === $city) {
            $this->assertNull($zipCity, 'Le Zip ne doit pas exister');
        } else {
            $this->assertNotNull($zipCity, 'Le Zip doit exister');
            $this->assertEquals($postalCode, $zipCity->getPostalCode(), 'Les codes postaux doivet concorder');
            $this->assertEquals($city, $zipCity->getName(), 'Les villes doivet concorder');
        }
    }

    private function assertCountry(Country $country = null, $value = null)
    {
        if (null === $value) {
            $this->assertNull($country, 'Le pays ne doit pas exister');
        } else {
            $this->assertNotNull($country, 'Le pays doit exister');
            $this->assertEquals($value, $country->getId(), 'Les pays doivet concorder');
        }
    }
}
