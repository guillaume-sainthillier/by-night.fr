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
use App\Entity\User;
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
        $entity->setExternalUpdatedAt($dto->externalUpdatedAt);
        if ($entity->getStartDate()?->getTimestamp() !== $dto->startDate?->getTimestamp()) {
            $entity->setStartDate($dto->startDate);
        }

        if ($entity->getEndDate()?->getTimestamp() !== $dto->endDate?->getTimestamp()) {
            $entity->setEndDate($dto->endDate);
        }

        $entity->setAddress($dto->address);
        if ($entity->getCreatedAt()?->getTimestamp() !== $dto->createdAt?->getTimestamp()) {
            $entity->setCreatedAt($dto->createdAt);
        }

        if ($entity->getCreatedAt()?->getTimestamp() !== $dto->updatedAt?->getTimestamp()) {
            $entity->setCreatedAt($dto->updatedAt);
        }

        $entity->setImage($dto->image);
        $entity->setImageFile($dto->imageFile);
        $entity->setFromData($dto->fromData);
        $entity->setCategory($dto->category);
        $entity->setName($dto->name);
        $entity->setDescription($dto->description);
        $entity->setHours($dto->hours);
        $entity->setPrices($dto->prices);
        $entity->setStatus($dto->status);
        $entity->setMailContacts($dto->emailContacts);
        $entity->setPhoneContacts($dto->phoneContacts);
        $entity->setWebsiteContacts($dto->websiteContacts);
        $entity->setLongitude($dto->longitude);
        $entity->setLatitude($dto->latitude);
        $entity->setPlaceName($dto->place?->name);
        $entity->setPlaceStreet($dto->place?->street);
        $entity->setPlaceExternalId($dto->place?->externalId);
        $entity->setPlaceCity($dto->place?->city?->name);
        $entity->setPlacePostalCode($dto->place?->city?->postalCode);
        $entity->setPlaceCountryName($dto->place?->country?->name);

        if (null !== $dto->user) {
            $userEntityProvider = $this->entityProviderHandler->getEntityProvider($dto->user::class);

            /** @var User|null $userEntity */
            $userEntity = $userEntityProvider->getEntity($dto->user);
            $entity->setUser($userEntity);
        }

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
