<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityFactory;

use App\Contracts\EntityFactoryInterface;
use App\Dto\PlaceDto;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Place;
use App\Entity\PlaceMetadata;
use App\Exception\UncreatableEntityException;
use App\Handler\EntityProviderHandler;

/**
 * @implements EntityFactoryInterface<PlaceDto, Place>
 */
final readonly class PlaceEntityFactory implements EntityFactoryInterface
{
    public function __construct(private EntityProviderHandler $entityProviderHandler)
    {
    }

    public function supports(string $dtoClassName): bool
    {
        return PlaceDto::class === $dtoClassName;
    }

    public function create(?object $entity, object $dto): object
    {
        if (null === $dto->name) {
            throw new UncreatableEntityException('Place has no name');
        }

        $entity ??= new Place();
        $entity->setName($dto->name);
        $entity->setLatitude($dto->latitude ?? $entity->getLatitude());
        $entity->setLongitude($dto->longitude ?? $entity->getLongitude());
        $entity->setStreet($dto->street ?? $entity->getStreet());
        $entity->setCityPostalCode($dto->city?->postalCode ?? $entity->getCityPostalCode());
        $entity->setCountryName($dto->country?->name ?? $entity->getCountryName());
        $entity->setCityName($dto->city?->name ?? $entity->getCityName());

        if (null !== $dto->city) {
            $cityEntityProvider = $this->entityProviderHandler->getEntityProvider($dto->city::class);
            /** @var City|null $city */
            $city = $cityEntityProvider->getEntity($dto->city);
            $entity->setCity($city);
        }

        if (null !== $dto->country) {
            $countryEntityProvider = $this->entityProviderHandler->getEntityProvider($dto->country::class);
            /** @var Country|null $country */
            $country = $countryEntityProvider->getEntity($dto->country);
            $entity->setCountry($country);
        }

        // Metadatas
        if (null !== $dto->getExternalId() && !$entity->hasMetadata($dto)) {
            $metadata = new PlaceMetadata();
            $metadata->setExternalId($dto->externalId);
            $metadata->setExternalOrigin($dto->externalOrigin);
            $entity->addMetadata($metadata);
        }

        return $entity;
    }
}
