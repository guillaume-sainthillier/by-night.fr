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
use App\Entity\EventTimesheet;
use App\Entity\Place;
use App\Entity\User;
use App\Handler\EntityProviderHandler;
use App\Repository\TagRepository;
use DateTimeImmutable;

final readonly class EventEntityFactory implements EntityFactoryInterface
{
    public function __construct(
        private EntityProviderHandler $entityProviderHandler,
        private EventImageUploadSubscriber $eventImageUploadSubscriber,
        private TagRepository $tagRepository,
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

        $entity->setExternalUpdatedAt(null === $dto->externalUpdatedAt ? null : DateTimeImmutable::createFromInterface($dto->externalUpdatedAt));

        // Sync timesheets from DTO
        $this->syncTimesheets($entity, $dto);

        $entity->setStartDate(null === $dto->startDate ? null : DateTimeImmutable::createFromInterface($dto->startDate));
        $entity->setEndDate(null === $dto->endDate ? null : DateTimeImmutable::createFromInterface($dto->endDate));

        $entity->setAddress($dto->address);
        if (null !== $dto->createdAt) {
            $entity->setCreatedAt($dto->createdAt);
        }

        if (null !== $dto->updatedAt) {
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

        // Convert category string to Tag entity
        if (null !== $dto->category && '' !== trim($dto->category)) {
            $entity->setCategory($this->tagRepository->findOrCreateByName($dto->category));
        } else {
            $entity->setCategory(null);
        }

        // Convert theme string to Tag entities
        $entity->clearThemes();
        if (null !== $dto->theme && '' !== trim($dto->theme)) {
            $themeNames = array_filter(array_map('trim', explode(',', $dto->theme)));
            foreach ($themeNames as $themeName) {
                $entity->addTheme($this->tagRepository->findOrCreateByName($themeName));
            }
        }

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

    /**
     * Sync timesheets from DTO to entity.
     * - Updates hours for existing timesheets (matching start/end dates)
     * - Adds new timesheets from DTO
     * - Removes timesheets not present in DTO
     */
    private function syncTimesheets(Event $entity, EventDto $dto): void
    {
        $existingTimesheets = $entity->getTimesheets()->toArray();

        // Build a map of existing timesheets by their start/end dates for quick lookup
        $existingMap = [];
        foreach ($existingTimesheets as $existing) {
            $key = $this->getTimesheetKey($existing->getStartAt(), $existing->getEndAt());
            $existingMap[$key] = $existing;
        }

        // Track which DTOs we've processed
        $processedKeys = [];

        // Process DTOs: update existing or add new
        foreach ($dto->timesheets as $timesheetDto) {
            $startAt = null === $timesheetDto->startAt ? null : DateTimeImmutable::createFromInterface($timesheetDto->startAt);
            $endAt = null === $timesheetDto->endAt ? null : DateTimeImmutable::createFromInterface($timesheetDto->endAt);
            $key = $this->getTimesheetKey($startAt, $endAt);

            if (isset($existingMap[$key])) {
                // Update existing timesheet hours
                $existingMap[$key]->setHours($timesheetDto->hours);
            } else {
                // Add new timesheet
                $timesheet = new EventTimesheet();
                $timesheet->setStartAt($startAt);
                $timesheet->setEndAt($endAt);
                $timesheet->setHours($timesheetDto->hours);
                $entity->addTimesheet($timesheet);
            }

            $processedKeys[] = $key;
        }

        // Remove timesheets that are not in the DTO
        foreach ($existingTimesheets as $existing) {
            $key = $this->getTimesheetKey($existing->getStartAt(), $existing->getEndAt());
            if (!\in_array($key, $processedKeys, true)) {
                $entity->removeTimesheet($existing);
            }
        }
    }

    /**
     * Generate a unique key for a timesheet based on start and end dates.
     */
    private function getTimesheetKey(?DateTimeImmutable $startAt, ?DateTimeImmutable $endAt): string
    {
        $start = $startAt?->format('Y-m-d H:i:s') ?? 'null';
        $end = $endAt?->format('Y-m-d H:i:s') ?? 'null';

        return $start . '|' . $end;
    }
}
