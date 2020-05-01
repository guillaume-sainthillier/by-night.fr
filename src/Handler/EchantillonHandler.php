<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use RuntimeException;
use App\Entity\Event;
use App\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;

class EchantillonHandler
{
    public $newPlaces;
    public $fbPlaces;
    /** @var EntityManagerInterface */
    private $em;
    /**
     * @var Place[][]
     */
    private $countryPlaces;

    /**
     * @var Place[][]
     */
    private $cityPlaces;

    /**
     * @var Event[]
     */
    private $events;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->init();
    }

    private function init()
    {
        $this->initEvents();
        $this->initPlaces();
    }

    private function initEvents()
    {
        $this->events = [];
    }

    private function initPlaces()
    {
        $this->countryPlaces = [];
        $this->cityPlaces = [];
    }

    public function clearEvents()
    {
        unset($this->events);
        $this->initEvents();
    }

    public function clearPlaces()
    {
        unset($this->newPlaces, $this->fbPlaces);
        $this->initPlaces();
    }

    public function prefetchPlaceEchantillons(array $events)
    {
        $cityIds = [];
        $countryIds = [];

        foreach ($events as $event) {
            /** @var Event $event */
            if ($event->getPlace() && $event->getPlace()->getCity()) {
                $cityIds[$event->getPlace()->getCity()->getId()] = true;
            } elseif ($event->getPlace() && $event->getPlace()->getCountry()) {
                $countryIds[$event->getPlace()->getCountry()->getId()] = true;
            }
        }

        $repoPlace = $this->em->getRepository(Place::class);

        //On prend toutes les places déjà connues par leur city ID
        if (\count($cityIds) > 0) {
            $places = $repoPlace->findBy([
                'city' => array_keys($cityIds),
            ]);

            foreach ($places as $place) {
                $this->addPlace($place);
            }
        }

        //On prend ensuite toutes les places selon leur localisation
        if (\count($countryIds) > 0) {
            $places = $repoPlace->findBy([
                'country' => array_keys($countryIds),
                'city' => null,
            ]);

            foreach ($places as $place) {
                $this->addPlace($place);
            }
        }
    }

    private function addPlace(Place $place)
    {
        $key = $place->getId() ?: spl_object_hash($place);

        if ($place->getCity() !== null) {
            $this->cityPlaces[$place->getCity()->getId()][$key] = $place;
        } elseif ($place->getCountry() !== null) {
            $this->countryPlaces[$place->getCountry()->getId()][$key] = $place;
        }
    }

    public function prefetchEventEchantillons(array $events)
    {
        $externalIds = [];
        foreach ($events as $event) {
            /** @var Event $event */
            if ($event->getId() || ($event->getUser() && !$event->getExternalId())) {
                continue;
            }

            if (!$event->getExternalId()) {
                throw new RuntimeException('Unable to find echantillon without an external ID');
            }

            $externalIds[$event->getExternalId()] = true;
        }

        if (\count($externalIds) > 0) {
            $repoEvent = $this->em->getRepository(Event::class);
            $candidates = $repoEvent->findBy(['externalId' => array_keys($externalIds)]);
            /** @var Event $candidate */
            foreach ($candidates as $candidate) {
                $this->addEvent($candidate);
            }
        }
    }

    private function addEvent(Event $event)
    {
        if ($event->getExternalId()) {
            $this->events[$event->getExternalId()] = $event;
        }
    }

    /**
     * @return Place[]
     */
    public function getPlaceEchantillons(Event $event)
    {
        if ($event->getPlace() !== null) {
            $place = $this->searchPlaceByExternalId($event->getPlace()->getExternalId());

            if ($place !== null) {
                return [$place];
            }
        }

        if ($event->getPlace() && $event->getPlace()->getCity()) {
            return $this->cityPlaces[$event->getPlace()->getCity()->getId()] ?? [];
        } elseif ($event->getPlace() && $event->getPlace()->getCountry()) {
            return $this->countryPlaces[$event->getPlace()->getCountry()->getId()] ?? [];
        }

        return [];
    }

    private function searchPlaceByExternalId(?string $placeExternalId): ?Place
    {
        if (null === $placeExternalId) {
            return null;
        }

        foreach (array_merge($this->cityPlaces, $this->countryPlaces) as $key => $places) {
            foreach ($places as $place) {
                /** @var Place $place */
                if ($place->getExternalId() === $placeExternalId) {
                    return $place;
                }
            }
        }

        return null;
    }

    public function getEventEchantillons(Event $event)
    {
        if ($event->getId() || ($event->getUser() && !$event->getExternalId())) {
            return [];
        }

        if ($event->getExternalId()) {
            return isset($this->events[$event->getExternalId()]) ? [$this->events[$event->getExternalId()]] : [];
        }

        return [];
    }

    public function addNewEvent(Event $event)
    {
        $this->addEvent($event);
        if ($event->getPlace() !== null) {
            $this->addPlace($event->getPlace());
        }
    }
}
