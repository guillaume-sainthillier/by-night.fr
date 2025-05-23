<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Entity\ZipCity;
use App\Tests\AppKernelTestCase;
use App\Utils\Comparator;
use PHPUnit\Framework\Attributes\DataProvider;

class ComparatorTest extends AppKernelTestCase
{
    private ?Comparator $comparator = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comparator = static::getContainer()->get(Comparator::class);
    }

    #[DataProvider('matchingScorePlaceProvider')]
    public function testMatchingScorePlace(?Place $a, ?Place $b, int $score): void
    {
        self::assertEquals($score, $this->comparator->getMatchingScorePlace($a, $b));
    }

    public static function matchingScorePlaceProvider(): iterable
    {
        $france = (new Country())->setId('FR');
        $toulouse = (new City())->setId(1)->setName('Toulouse')->setCountry($france);

        return [
            // Basic Place check
            [null, null, 0],
            [null, new Place(), 0],
            [new Place(), null, 0],
            [new Place(), new Place(), 0],

            // By External ids check
            [(new Place())->setId(1), (new Place())->setId(1), 100],
            [(new Place())->setExternalId('EXT-1'), (new Place())->setExternalId('EXT-1'), 100],
            [(new Place())->setExternalId('EXT-1'), (new Place())->setExternalId('EXT-2'), 0],
            [new Place(), (new Place())->setExternalId('EXT-1'), 0],
            [(new Place())->setExternalId('EXT-1'), new Place(), 0],
            [(new Place())->setId(1)->setExternalId('EXT-1'), (new Place())->setId(2)->setExternalId('EXT-1'), 100],
            [(new Place())->setId(1)->setExternalId('EXT-1'), (new Place())->setId(1)->setExternalId('EXT-2'), 100],

            // By city check
            [new Place(), (new Place())->setCity($toulouse), 0],
            [(new Place())->setCity($toulouse), new Place(), 0],
            [(new Place())->setCity($toulouse), (new Place())->setCity($toulouse), 0],
            [(new Place())->setCity($toulouse), (new Place())->setCity($toulouse)->setName('Secret place'), 0],
            [(new Place())->setCity($toulouse)->setName('Secret place'), (new Place())->setCity($toulouse), 0],
            [(new Place())->setCity($toulouse)->setName('Bikini')->setStreet('3 Rue Théodore Monod'), (new Place())->setCity($toulouse)->setName('Le bikini')->setStreet('rue theodore monod'), 100],
            [(new Place())->setCity($toulouse)->setName('Bikini'), (new Place())->setCity($toulouse)->setName('Le bikini'), 90],

            // By country check
            [new Place(), (new Place())->setCountry($france), 0],
            [(new Place())->setCountry($france), new Place(), 0],
            [(new Place())->setCountry($france), (new Place())->setCountry($france), 0],
            [(new Place())->setCountry($france), (new Place())->setCountry($france)->setName('Secret place'), 0],
            [(new Place())->setCountry($france)->setName('Secret place'), (new Place())->setCountry($france), 0],
            [(new Place())->setCountry($france)->setName('Bikini')->setStreet('3 Rue Théodore Monod'), (new Place())->setCountry($france)->setName('Le bikini')->setStreet('rue theodore monod'), 100],
            [(new Place())->setCountry($france)->setName('Bikini'), (new Place())->setCountry($france)->setName('Le bikini'), 90],
        ];
    }

    #[DataProvider('bestPlaceProvider')]
    public function testBestPlace(?int $expectedId, Place $place, array $places): void
    {
        $original = $place;
        $place = $this->comparator->getBestPlace($places, $place);
        if (null === $expectedId) {
            self::assertNull($place, 'Original : ' . $original->getName());
        } else {
            self::assertNotNull($place, 'Original : ' . $original->getName());
            self::assertEquals($expectedId, $place->getId(), 'Original : ' . $original->getName());
        }
    }

    public static function bestPlaceProvider(): iterable
    {
        $france = (new Country())->setId('FR');
        $toulouse = (new City())->setId(1)->setName('Toulouse')->setCountry($france);
        $toulouseZip = (new ZipCity())->setId(1)->setName('Toulouse')->setPostalCode('31000')->setParent($toulouse);
        $toulouseZip2 = (new ZipCity())->setId(2)->setName('Toulouse')->setPostalCode('31500')->setParent($toulouse);

        $dynamo = (new Place())->setId(1)->setName('Dynamo')->setStreet('6 rue Amélie')->setZipCity(clone $toulouseZip)->setCity($toulouse);
        $bikini = (new Place())->setId(2)->setName('Le bikini')->setZipCity($toulouseZip)->setCity($toulouse);
        $moloko = (new Place())->setId(3)->setName('Moloko')->setStreet('6 Rue Joutx Aigues')->setZipCity($toulouseZip)->setCity($toulouse);
        $moloko2 = clone $moloko;
        $moloko2->setId(4);

        $macdo = (new Place())->setId(5)->setName("McDonald's Capitole")->setStreet('Place du Capitole')->setZipCity(clone $toulouseZip)->setCity($toulouse);

        return [
            [1, (new Place())->setName('La Dynamo')->setStreet('6 rue Amelie')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
            [1, (new Place())->setName('La Dynamo')->setStreet('6 rue Amelie')->setZipCity($toulouseZip2)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
            [1, (new Place())->setName('La Dynamo')->setStreet('6 rue Amelie')->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],

            [2, (new Place())->setName('Bikini, Toulouse')->setCity($toulouse), [$moloko, $dynamo, $macdo, $bikini]],

            [3, (new Place())->setName('Moloko')->setStreet('6 Rue Joutx Aigues')->setZipCity($toulouseZip2)->setCity($toulouse), [$dynamo, $bikini, $moloko, $macdo, $moloko2]],
            [4, (new Place())->setName('Moloko')->setStreet('6 Rue Joutx Aigues')->setZipCity($toulouseZip2)->setCity($toulouse), [$dynamo, $bikini, $moloko2, $macdo, $moloko]],

            [null, (new Place())->setName("McDonald's Esquirol")->setStreet('43444 Place Esquirol')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $dynamo, $macdo]],
            [null, (new Place())->setName('La gouaille')->setStreet('6 Rue Joutx Aigues')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
            [null, (new Place())->setName('Autre Lieu Que La Dynamo')->setStreet('6 rue Amélie')->setZipCity($toulouseZip)->setCity($toulouse), [$bikini, $moloko, $macdo, $dynamo]],
        ];
    }

    #[DataProvider('sanitizeRueProvider')]
    public function testSanitizeRue(?string $actual, ?string $expected): void
    {
        self::assertEquals($expected, $this->comparator->sanitizeRue($actual), 'Original : ' . $actual);
    }

    public static function sanitizeRueProvider(): array
    {
        return [
            ['1908 Route de Lamasquère', '1908 route de lamasquere'],
            ['1908 Route  de Lamasquère', '1908 route de lamasquere'],
        ];
    }

    #[DataProvider('sanitizeVilleProvider')]
    public function testSanitizeVille(string $actual, string $expected): void
    {
        self::assertEquals($expected, $this->comparator->sanitizeVille($actual), 'Original : ' . $actual);
    }

    public static function sanitizeVilleProvider(): array
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
