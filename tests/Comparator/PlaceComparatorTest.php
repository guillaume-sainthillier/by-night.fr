<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Comparator;

use App\Comparator\PlaceComparator;
use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\PlaceDto;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Utils\PlaceNameNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Two sources name the same venue their own way: the comparator decides which imported
 * place is a venue already known in the same town.
 */
final class PlaceComparatorTest extends TestCase
{
    private PlaceComparator $comparator;

    private Country $france;

    private City $cugnaux;

    protected function setUp(): void
    {
        $this->comparator = new PlaceComparator(new PlaceNameNormalizer());
        $this->france = new Country()->setId('FR')->setName('France');
        $this->cugnaux = new City()->setId(31157)->setName('Cugnaux')->setCountry($this->france);
    }

    public function testTheSameNameAndStreetIsTheSamePlace(): void
    {
        $matching = $this->comparator->getMatching(
            $this->place('Médiathèque', '12 rue de la République'),
            $this->dto('Médiathèque', '12, Rue de la République'),
        );

        self::assertSame(100.0, $matching?->getConfidence());
    }

    public function testTheSameNameOnAnotherStreetIsLikelyTheSamePlace(): void
    {
        $matching = $this->comparator->getMatching(
            $this->place('Médiathèque', '12 rue de la République'),
            $this->dto('Médiathèque', 'Place du Marché'),
        );

        self::assertSame(90.0, $matching?->getConfidence());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideSurfaceForms(): iterable
    {
        yield 'leading article' => ['Le Bikini', 'Bikini'];
        yield 'case and accents' => ['MÉDIATHÈQUE', 'Médiathèque'];
        yield 'town name appended' => ['Médiathèque Cugnaux', 'Médiathèque'];
        // 935 pairs of places of the dev base differ by this suffix alone
        yield 'of the town' => ['Médiathèque de Cugnaux', 'Médiathèque'];
    }

    #[DataProvider('provideSurfaceForms')]
    public function testSurfaceFormsOfOneNameAreTheSamePlace(string $known, string $imported): void
    {
        self::assertNotNull($this->comparator->getMatching($this->place($known), $this->dto($imported)));
    }

    public function testAnotherNameIsAnotherPlace(): void
    {
        self::assertNull($this->comparator->getMatching($this->place('Le Bikini'), $this->dto('Le Metronum')));
    }

    public function testThePlaceOfAnotherTownIsAnotherPlace(): void
    {
        $dto = $this->dto('Médiathèque');
        $dto->city->entityId = 31555;

        self::assertNull($this->comparator->getMatching($this->place('Médiathèque'), $dto));
    }

    public function testWithoutTownTheNamesakesOfAnotherPostalCodeAreOtherPlaces(): void
    {
        $known = new Place()->setName('Salle des fêtes')->setCountry($this->france);
        $known->setCityPostalCode('31270');
        $known->setCityName('Cugnaux');

        self::assertNotNull($this->comparator->getMatching($known, $this->townlessDto('Salle des fêtes', '31270', 'Cugnaux')));
        self::assertNull($this->comparator->getMatching($known, $this->townlessDto('Salle des fêtes', '81000', 'Albi')));
        self::assertNull($this->comparator->getMatching($known, $this->townlessDto('Salle des fêtes', '31270', 'Villeneuve-Tolosane')));
    }

    private function place(string $name, ?string $street = null): Place
    {
        return new Place()
            ->setName($name)
            ->setStreet($street)
            ->setCity($this->cugnaux)
            ->setCountry($this->france);
    }

    private function dto(string $name, ?string $street = null): PlaceDto
    {
        $country = new CountryDto();
        $country->entityId = 'FR';

        $city = new CityDto();
        $city->entityId = 31157;
        $city->name = 'Cugnaux';
        $city->postalCode = '31270';
        $city->country = $country;

        $place = new PlaceDto();
        $place->name = $name;
        $place->street = $street;
        $place->city = $city;
        $place->country = $country;

        return $place;
    }

    private function townlessDto(string $name, string $postalCode, string $town): PlaceDto
    {
        $place = $this->dto($name);
        $place->city->entityId = null;
        $place->city->postalCode = $postalCode;
        $place->city->name = $town;

        return $place;
    }
}
