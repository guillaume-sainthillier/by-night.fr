<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityFactory;

use App\Contracts\EntityFactoryInterface;
use App\Dto\PlaceDto;
use App\Entity\Place;
use App\Entity\PlaceMetadata;
use App\Handler\EntityProviderHandler;

class PlaceEntityFactory implements EntityFactoryInterface
{
    /** @var EntityProviderHandler */
    private $entityProviderHandler;

    public function __construct(EntityProviderHandler $entityProviderHandler)
    {
        $this->entityProviderHandler = $entityProviderHandler;
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $resourceClass): bool
    {
        return PlaceDto::class === $resourceClass;
    }

    /**
     * {@inheritDoc}
     */
    public function create(?object $entity, object $dto): object
    {
        $entity = $entity ?? new Place();
        \assert($entity instanceof Place);
        \assert($dto instanceof PlaceDto);

        if (null === $entity) {
            dd($dto);
        }
        $entity->setNom($dto->name);
        $entity->setLatitude($dto->latitude ?? $entity->getLatitude());
        $entity->setLongitude($dto->longitude ?? $entity->getLongitude());
        $entity->setRue($dto->street ?? $entity->getRue());
        $entity->setCodePostal($dto->postalCode ?? $entity->getCodePostal());
        $entity->setCountryName($dto->country->name ?? $entity->getCountryName());
        $entity->setVille($dto->city->name ?? $entity->getVille());

        if (null !== $dto->city) {
            $cityEntityProvider = $this->entityProviderHandler->getEntityProvider(\get_class($dto->city));
            $city = $cityEntityProvider->getEntity($dto->city);
            $entity->setCity($city);
        }

        if (null !== $dto->country) {
            $countryEntityProvider = $this->entityProviderHandler->getEntityProvider(\get_class($dto->country));
            $country = $countryEntityProvider->getEntity($dto->country);
            $entity->setCountry($country);
        }

        //Metadatas
        if (null !== $dto->getExternalId() && !$entity->hasMetadata($dto)) {
            $metadata = new PlaceMetadata();
            $metadata->setExternalId($dto->externalId);
            $metadata->setExternalOrigin($dto->externalOrigin);
            $entity->addMetadata($metadata);
        }

        return $entity;
    }
}
