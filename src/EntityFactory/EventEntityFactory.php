<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EntityFactory;

use App\Contracts\EntityFactoryInterface;
use App\Dto\EventDto;
use App\Entity\Event;
use App\Entity\Place;
use App\Handler\EntityProviderHandler;

class EventEntityFactory implements EntityFactoryInterface
{
    public function __construct(private EntityProviderHandler $entityProviderHandler)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $resourceClass): bool
    {
        return EventDto::class === $resourceClass;
    }

    /**
     * {@inheritDoc}
     */
    public function create(?object $entity, object $dto): object
    {
        $entity ??= new Event();
        \assert($entity instanceof Event);
        \assert($dto instanceof EventDto);

        $entity->setExternalId($dto->externalId);
        $entity->setExternalOrigin($dto->externalOrigin);
        $entity->setAdresse($dto->address);
        $entity->setExternalUpdatedAt($dto->externalUpdatedAt);
        $entity->setCategorieManifestation($dto->category);
        $entity->setDescriptif($dto->description);
        $entity->setModificationDerniereMinute($dto->status);
        $entity->setMailContacts($dto->emailContacts);
        $entity->setName($dto->name);
        $entity->setPlaceName($dto->place?->name);
        $entity->setPlaceCity($dto->place?->city->name);
        $entity->setPlaceCountryName($dto->place?->country->name);
        $entity->setPlacePostalCode($dto->place?->postalCode);
        $entity->setPlaceStreet($dto->place?->street);
        $entity->setPlaceExternalId($dto->place?->externalId);
        // $entity->setPlaceFacebookId();
        $entity->setLongitude($dto->longitude);
        $entity->setLatitude($dto->latitude);
        $entity->setNom($dto->name);
        $entity->setHoraires($dto->hours);

        if (null !== $dto->place) {
            $placeEntityProvider = $this->entityProviderHandler->getEntityProvider($dto->place::class);

            /** @var Place|null $placeEntity */
            $placeEntity = $placeEntityProvider->getEntity($dto->place);
            $entity->setPlace($placeEntity);
            $entity->setPlaceCountry($placeEntity?->getCountry());
        }

        return $entity;
    }
}
