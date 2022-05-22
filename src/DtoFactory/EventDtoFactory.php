<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\DtoFactory;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Dto\UserDto;
use App\Entity\Event;

class EventDtoFactory
{
    public function create(Event $entity): EventDto
    {
        $event = new EventDto();
        $event->entityId = $entity->getId();
        $event->externalId = $entity->getExternalId();
        $event->externalOrigin = $entity->getExternalOrigin();
        $event->address = $entity->getAdresse();
        $event->externalUpdatedAt = $entity->getExternalUpdatedAt();
        $event->category = $entity->getCategorieManifestation();
        $event->name = $entity->getNom();
        $event->description = $entity->getDescriptif();
        $event->hours = $entity->getHoraires();
        $event->prices = $entity->getTarif();
        $event->status = $entity->getModificationDerniereMinute();
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

        if ($entity->getUser()) {
            $user = new UserDto();
            $user->entityId = $entity->getUser()->getId();
            $event->user = $user;
        }

        if ($entity->getPlaceCountry()) {
            $country = new CountryDto();
            $country->code = $entity->getPlaceCountry()->getId();

            $city->country = $country;
            $place->country = $country;
        }

        $place->city = $city;

        $event->place = $place;

        return $event;
    }
}
