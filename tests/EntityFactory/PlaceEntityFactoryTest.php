<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EntityFactory;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\EntityFactory\PlaceEntityFactory;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\PlaceFactory;
use App\Handler\EntityProviderHandler;
use App\Tests\AppKernelTestCase;
use App\Utils\PlaceNameNormalizer;
use Override;

final class PlaceEntityFactoryTest extends AppKernelTestCase
{
    private PlaceEntityFactory $factory;

    private RecordingLogger $logger;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = new RecordingLogger();
        $this->factory = new PlaceEntityFactory(
            self::getContainer()->get(EntityProviderHandler::class),
            self::getContainer()->get(PlaceNameNormalizer::class),
            $this->logger,
        );
    }

    public function testAnUnresolvedLocationDoesNotEraseTheOneAlreadyStored(): void
    {
        $france = CountryFactory::france()->create();
        $toulouse = CityFactory::createOne(['name' => 'Toulouse', 'country' => $france]);
        $place = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $france]);

        // Nothing was prefetched for this DTO, so neither its city nor its country resolves
        $entity = $this->factory->create($place, self::placeDto('ZZ'));

        self::assertSame($place, $entity);
        self::assertSame('FR', $entity->getCountry()?->getId(), 'The stored country survives an import that cannot resolve one');
        self::assertSame($toulouse->getId(), $entity->getCity()?->getId(), 'The stored city survives too');
        self::assertSame([], $this->logger->records, 'Nothing is lost, nothing to report');
    }

    public function testANewPlaceWithoutResolvableCountryIsReported(): void
    {
        $entity = $this->factory->create(null, self::placeDto('ZZ'));

        self::assertInstanceOf(Place::class, $entity);
        self::assertNull($entity->getCountry());
        self::assertCount(1, $this->logger->records);
        self::assertSame('warning', $this->logger->records[0]['level']);
        self::assertStringContainsString('without country', $this->logger->records[0]['message']);
    }

    private static function placeDto(string $countryCode): PlaceDto
    {
        $country = new CountryDto();
        $country->code = $countryCode;

        $city = new CityDto();
        $city->name = 'Nulle-Part';
        $city->postalCode = '99999';
        $city->country = $country;

        $dto = new PlaceDto();
        $dto->name = 'Le Bikini';
        $dto->externalId = 'bikini';
        $dto->externalOrigin = 'test';
        $dto->city = $city;
        $dto->country = $country;

        return $dto;
    }
}
