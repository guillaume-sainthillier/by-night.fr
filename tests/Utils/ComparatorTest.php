<?php

namespace App\Tests\Utils;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Entity\ZipCity;
use App\Tests\ContainerTestCase;
use App\Utils\Comparator;

class ComparatorTest extends ContainerTestCase
{
    /**
     * @var Comparator
     */
    protected $comparator;

    protected function setUp()
    {
        parent::setUp();

        $this->comparator = static::$container->get(Comparator::class);
    }

    /**
     * @dataProvider matchingScorePlaceProvider
     */
    public function testMatchingScorePlace(?Place $a, ?Place $b, int $score)
    {
        $this->assertEquals($score, $this->comparator->getMatchingScorePlace($a, $b));
    }

    public function matchingScorePlaceProvider()
    {
        $france = (new Country())->setId('FR');
        $toulouse = (new City())->setId(1)->setName('Toulouse')->setCountry($france);

        return [
            //Basic Place check
            [null, null, 0],
            [null, new Place(), 0],
            [new Place(), null, 0],
            [new Place(), new Place(), 0],

            //By External ids check
            [(new Place())->setId(1), (new Place())->setId(1), 100],
            [(new Place())->setExternalId('EXT-1'), (new Place())->setExternalId('EXT-1'), 100],
            [(new Place())->setExternalId('EXT-1'), (new Place())->setExternalId('EXT-2'), 0],
            [(new Place()), (new Place())->setExternalId('EXT-1'), 0],
            [(new Place())->setExternalId('EXT-1'), (new Place()), 0],
            [(new Place())->setId(1)->setExternalId('EXT-1'), (new Place())->setId(2)->setExternalId('EXT-1'), 100],
            [(new Place())->setId(1)->setExternalId('EXT-1'), (new Place())->setId(1)->setExternalId('EXT-2'), 100],

            //By city check
            [(new Place()), (new Place())->setCity($toulouse), 0],
            [(new Place())->setCity($toulouse), (new Place()), 0],
            [(new Place())->setCity($toulouse), (new Place())->setCity($toulouse), 0],
            [(new Place())->setCity($toulouse), (new Place())->setCity($toulouse)->setNom('Secret place'), 0],
            [(new Place())->setCity($toulouse)->setNom('Secret place'), (new Place())->setCity($toulouse), 0],
            [(new Place())->setCity($toulouse)->setNom('Bikini')->setRue('3 Rue Théodore Monod'), (new Place())->setCity($toulouse)->setNom('Le bikini')->setRue('rue theodore monod'), 100],
            [(new Place())->setCity($toulouse)->setNom('Bikini'), (new Place())->setCity($toulouse)->setNom('Le bikini'), 90],

            //By country check
            [(new Place()), (new Place())->setCountry($france), 0],
            [(new Place())->setCountry($france), (new Place()), 0],
            [(new Place())->setCountry($france), (new Place())->setCountry($france), 0],
            [(new Place())->setCountry($france), (new Place())->setCountry($france)->setNom('Secret place'), 0],
            [(new Place())->setCountry($france)->setNom('Secret place'), (new Place())->setCountry($france), 0],
            [(new Place())->setCountry($france)->setNom('Bikini')->setRue('3 Rue Théodore Monod'), (new Place())->setCountry($france)->setNom('Le bikini')->setRue('rue theodore monod'), 100],
            [(new Place())->setCountry($france)->setNom('Bikini'), (new Place())->setCountry($france)->setNom('Le bikini'), 90],
        ];
    }

    /**
     * @dataProvider bestPlaceProvider
     */
    public function testBestPlace(?int $expectedId, Place $place, array $places)
    {
        $original = $place;
        $place = $this->comparator->getBestPlace($places, $place);
        if (null === $expectedId) {
            $this->assertNull($place, 'Original : ' . $original->getNom());
        } else {
            $this->assertNotNull($place, 'Original : ' . $original->getNom());
            $this->assertEquals($expectedId, $place->getId(), 'Original : ' . $original->getNom());
        }
    }

    public function bestPlaceProvider()
    {
        $france = (new Country())->setId('FR');
        $toulouse = (new City())->setId(1)->setName('Toulouse')->setCountry($france);
        $toulouseZip = (new ZipCity())->setId(1)->setName('Toulouse')->setPostalCode('31000')->setParent($toulouse);
        $toulouseZip2 = (new ZipCity())->setId(2)->setName('Toulouse')->setPostalCode('31500')->setParent($toulouse);

        $dynamo = (new Place())->setId(1)->setNom('Dynamo')->setRue('6 rue Amélie')->setZipCity(clone $toulouseZip)->setCity($toulouse);
        $bikini = (new Place())->setId(2)->setNom('Le bikini')->setZipCity($toulouseZip)->setCity($toulouse);
        $moloko = (new Place())->setId(3)->setNom('Moloko')->setRue('6 Rue Joutx Aigues')->setZipCity($toulouseZip)->setCity($toulouse);
        $moloko2 = clone $moloko;
        $moloko2->setId(4);
        $macdo = (new Place())->setId(5)->setNom('McDonald\'s Capitole')->setRue('Place du Capitole')->setZipCity(clone $toulouseZip)->setCity($toulouse);

        return [
            [1, (new Place())->setNom('La Dynamo')->setRue('6 rue Amelie')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
            [1, (new Place())->setNom('La Dynamo')->setRue('6 rue Amelie')->setZipCity($toulouseZip2)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
            [1, (new Place())->setNom('La Dynamo')->setRue('6 rue Amelie')->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],

            [2, (new Place())->setNom('Bikini, Toulouse')->setCity($toulouse), [$moloko, $dynamo, $macdo, $bikini]],

            [3, (new Place())->setNom('Moloko')->setRue('6 Rue Joutx Aigues')->setZipCity($toulouseZip2)->setCity($toulouse), [$dynamo, $bikini, $moloko, $macdo, $moloko2]],
            [4, (new Place())->setNom('Moloko')->setRue('6 Rue Joutx Aigues')->setZipCity($toulouseZip2)->setCity($toulouse), [$dynamo, $bikini, $moloko2, $macdo, $moloko]],

            [null, (new Place())->setNom('McDonald\'s Esquirol')->setRue('43444 Place Esquirol')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $dynamo, $macdo]],
            [null, (new Place())->setNom('La gouaille')->setRue('6 Rue Joutx Aigues')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
            [null, (new Place())->setNom('Autre Lieu Que La Dynamo')->setRue('6 rue Amélie')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
        ];
    }

    /**
     * @dataProvider sanitizeRueProvider
     */
    public function testSanitizeRue($actual, $expected)
    {
        $this->assertEquals($expected, $this->comparator->sanitizeRue($actual), 'Original : ' . $actual);
    }

    public function sanitizeRueProvider()
    {
        return [
            ['1908 Route de Lamasquère', '1908 route de lamasquere'],
            ['1908 Route  de Lamasquère', '1908 route de lamasquere'],
        ];
    }

    /**
     * @dataProvider sanitizeVilleProvider
     */
    public function testSanitizeVille($actual, $expected)
    {
        $this->assertEquals($expected, $this->comparator->sanitizeVille($actual), 'Original : ' . $actual);
    }

    public function sanitizeVilleProvider()
    {
        return [
            ['saint-lys', 'saintlys'],
            ['SAINT-LYS', 'saintlys'],
            ['SAINT LYS', 'saintlys'],
            ['ST LYS', 'saintlys'],
            ['ST-LYS', 'saintlys'],
            ['bourg st bernard', 'bourgsaintbernard'],
            ['bourg -st bernard', 'bourgsaintbernard'],
            ['bourg -st -bernard', 'bourgsaintbernard'],
            ['bourg - st - bernard', 'bourgsaintbernard'],
            ['bourg- st -bernard', 'bourgsaintbernard'],
            ['bourg-st-bernard', 'bourgsaintbernard'],
        ];
    }
}
