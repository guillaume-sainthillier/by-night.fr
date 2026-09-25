<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Dependency;

use App\Contracts\DependencyObjectInterface;
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;
use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use PHPUnit\Framework\TestCase;

/**
 * A catalogue merges the DTOs of a batch sharing a key (DependencyObjectInterface::getUniqueKey()):
 * the first one is resolved, the others are its aliases and get the same entity id. Merging
 * too little costs a lookup, merging too much files a DTO under another one's entity.
 */
final class DependencyCatalogueTest extends TestCase
{
    public function testACountryCodeIsOneDependencyWhateverItsCaseOrPadding(): void
    {
        $canonical = $this->country('FR');
        $catalogue = $this->catalogue($canonical, $this->country(' fr '), $this->country('Fr'));

        self::assertCount(1, $catalogue->all());
        self::assertCount(2, $catalogue->getAliases($canonical));
    }

    public function testCountriesWithoutCodeAreToldApartByTheirName(): void
    {
        $catalogue = $this->catalogue($this->country('', 'Belgique'), $this->country('', 'France'), $this->country(' ', 'france'));

        self::assertCount(2, $catalogue->all());
    }

    public function testACountryWithNothingUsableStandsAlone(): void
    {
        $catalogue = $this->catalogue($this->country(''), $this->country(null));

        self::assertCount(2, $catalogue->all());
    }

    public function testTheFieldsOfAPlaceDoNotRunIntoEachOther(): void
    {
        $catalogue = $this->catalogue($this->place('Salle A-B', 'Rue C'), $this->place('Salle A', 'B-Rue C'));

        self::assertCount(2, $catalogue->all());
    }

    public function testAPlaceIsOneDependencyWhateverItsCase(): void
    {
        $catalogue = $this->catalogue($this->place('Le Bikini', 'Rue Hermès'), $this->place('LE BIKINI', 'rue hermès'));

        self::assertCount(1, $catalogue->all());
    }

    public function testASourceIdTellsPlacesApartWhateverTheirName(): void
    {
        $first = $this->place('Le Bikini', null);
        $first->externalOrigin = 'openagenda';
        $first->externalId = '1';
        $second = $this->place('Le Bikini', null);
        $second->externalOrigin = 'openagenda';
        $second->externalId = '2';

        self::assertCount(2, $this->catalogue($first, $second)->all());
    }

    public function testATagIsOneDependencyWhateverItsCaseAndAccents(): void
    {
        $catalogue = $this->catalogue(TagDto::fromString('Théâtre'), TagDto::fromString('THEATRE '));

        self::assertCount(1, $catalogue->all());
    }

    private function catalogue(DependencyObjectInterface ...$objects): DependencyCatalogue
    {
        $catalogue = new DependencyCatalogue();
        foreach ($objects as $object) {
            $catalogue->add(new Dependency($object));
        }

        return $catalogue;
    }

    private function country(?string $code, ?string $name = null): CountryDto
    {
        $dto = new CountryDto();
        $dto->code = $code;
        $dto->name = $name;

        return $dto;
    }

    private function place(string $name, ?string $street): PlaceDto
    {
        $city = new CityDto();
        $city->name = 'Toulouse';
        $city->postalCode = '31000';
        $city->country = $this->country('FR');

        $dto = new PlaceDto();
        $dto->name = $name;
        $dto->street = $street;
        $dto->city = $city;

        return $dto;
    }
}
