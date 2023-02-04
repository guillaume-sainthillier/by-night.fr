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
use App\Doctrine\EventSubscriber\EventImageUploadSubscriber;
use App\Dto\EventDto;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Handler\EntityProviderHandler;

class EventEntityFactory implements EntityFactoryInterface
{
    public function __construct(
        private readonly EntityProviderHandler $entityProviderHandler,
        private readonly EventImageUploadSubscriber $eventImageUploadSubscriber,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function supports(string $dtoClassName): bool
    {
        return EventDto::class === $dtoClassName;
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

        if ($entity->getExternalUpdatedAt()?->format('Y-m-d H:i:s') !== $dto->externalUpdatedAt?->format('Y-m-d H:i:s')) {
            $entity->setExternalUpdatedAt($dto->externalUpdatedAt);
        }

        if ($entity->getStartDate()?->format('Y-m-d H:i:s') !== $dto->startDate?->format('Y-m-d H:i:s')) {
            $entity->setStartDate($dto->startDate);
        }

        if ($entity->getEndDate()?->format('Y-m-d H:i:s') !== $dto->endDate?->format('Y-m-d H:i:s')) {
            $entity->setEndDate($dto->endDate);
        }

        $entity->setAddress($dto->address);
        if ($dto->createdAt && $entity->getCreatedAt()?->format('Y-m-d H:i:s') !== $dto->createdAt?->format('Y-m-d H:i:s')) {
            $entity->setCreatedAt($dto->createdAt);
        }

        if ($dto->updatedAt && $entity->getUpdatedAt()?->format('Y-m-d H:i:s') !== $dto->updatedAt?->format('Y-m-d H:i:s')) {
            $entity->setUpdatedAt($dto->updatedAt);
        }

        $entity->getImage()->setDimensions($dto->image?->getDimensions());
        $entity->getImage()->setMimeType($dto->image?->getMimeType());
        $entity->getImage()->setName($dto->image?->getName());
        $entity->getImage()->setOriginalName($dto->image?->getOriginalName());
        $entity->getImage()->setSize($dto->image?->getSize());
        $entity->setImageFile($dto->imageFile);

        if ($entity->getUrl() !== $dto->imageUrl || null === $entity->getImageSystem()->getName()) {
            $entity->setUrl($dto->imageUrl);
            $this->eventImageUploadSubscriber->handleEvent($entity);
        }

        $entity->setFromData($dto->fromData);
        $entity->setSource($dto->source);
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
