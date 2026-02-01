<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\DtoFactory;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Dto\UserDto;
use App\Entity\Event;

final class EventDtoFactory
{
    public function create(Event $entity): EventDto
    {
        $event = new EventDto();
        $event->fromData = $entity->getFromData();
        $event->source = $entity->getSource();
        $event->entityId = $entity->getId();
        $event->externalId = $entity->getExternalId();
        $event->externalOrigin = $entity->getExternalOrigin();
        $event->externalUpdatedAt = $entity->getExternalUpdatedAt();
        $event->startDate = clone $entity->getStartDate();
        $event->endDate = clone $entity->getEndDate();
        $event->createdAt = clone $entity->getCreatedAt();
        $event->updatedAt = clone $entity->getUpdatedAt();
        $event->imageFile = $entity->getImageFile();
        $event->image = $entity->getImage();
        $event->imageUrl = $entity->getUrl();
        $event->fromData = $entity->getFromData();
        $event->address = $entity->getAddress();
        if (null !== $entity->getCategory()) {
            $event->category = TagDto::fromEntity($entity->getCategory());
        }

        foreach ($entity->getThemes() as $theme) {
            $event->themes[] = TagDto::fromEntity($theme);
        }

        $event->name = $entity->getName();
        $event->description = $entity->getDescription();
        $event->hours = $entity->getHours();
        $event->prices = $entity->getPrices();
        $event->status = $entity->getStatus();
        $event->statusMessage = $entity->getStatusMessage();
        $event->emailContacts = $entity->getMailContacts();
        $event->phoneContacts = $entity->getPhoneContacts();
        $event->websiteContacts = $entity->getWebsiteContacts();
        $event->latitude = $entity->getLatitude();
        $event->longitude = $entity->getLongitude();

        $place = new PlaceDto();
        $place->name = $entity->getPlaceName();
        $place->street = $entity->getPlaceStreet();
        $place->externalId = $entity->getPlaceExternalId();
        $place->externalOrigin = $entity->getExternalOrigin();

        $city = new CityDto();
        $city->name = $entity->getPlaceCity();
        $city->postalCode = $entity->getPlacePostalCode();

        if (null !== $entity->getUser()) {
            $user = new UserDto();
            $user->entityId = $entity->getUser()->getId();
            $event->user = $user;
        }

        if (null !== $entity->getPlaceCountry()) {
            $country = new CountryDto();
            $country->entityId = $entity->getPlaceCountry()->getId();
            $country->code = $entity->getPlaceCountry()->getId();

            $city->country = $country;
            $place->country = $country;
        }

        $place->city = $city;

        $event->place = $place;

        // Map timesheets from entity to DTO
        foreach ($entity->getTimesheets() as $timesheetEntity) {
            $timesheetDto = new EventTimesheetDto();
            $timesheetDto->startAt = $timesheetEntity->getStartAt() ? clone $timesheetEntity->getStartAt() : null;
            $timesheetDto->endAt = $timesheetEntity->getEndAt() ? clone $timesheetEntity->getEndAt() : null;
            $timesheetDto->hours = $timesheetEntity->getHours();

            $event->timesheets[] = $timesheetDto;
        }

        return $event;
    }
}
