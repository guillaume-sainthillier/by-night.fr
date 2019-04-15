<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 01/12/2016
 * Time: 20:22.
 */

namespace App\Handler;

use App\Entity\Agenda;
use App\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;

class EchantillonHandler
{
    /** @var EntityManagerInterface */
    private $em;
    /**
     * @var Place[]
     */
    private $countryPlaces;

    /**
     * @var Place[]
     */
    private $cityPlaces;

    /**
     * @var Agenda[]
     */
    private $agendas;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->init();
    }

    private function initEvents()
    {
        $this->agendas = [];
    }

    private function initPlaces()
    {
        $this->countryPlaces = [];
        $this->cityPlaces = [];
    }

    private function init()
    {
        $this->initEvents();
        $this->initPlaces();
    }

    public function clearEvents()
    {
        unset($this->agendas);
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
            /** @var Agenda $event */
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
                'city' => null
            ]);

            foreach ($places as $place) {
                $this->addPlace($place);
            }
        }
    }

    public function prefetchEventEchantillons(array $events)
    {
        $externalIds = [];
        foreach ($events as $event) {
            if ($event->getId() || $event->getUser()) {
                continue;
            }

            if (!$event->getExternalId()) {
                throw new \RuntimeException("Unable to find echantillon without an external ID");
            }

            $externalIds[$event->getExternalId()] = true;
        }

        if (count($externalIds) > 0) {
            $repoAgenda = $this->em->getRepository(Place::class);
            $candidates = $repoAgenda->findBy(['externalId' => array_keys($externalIds)]);
            foreach ($candidates as $candidate) {
                /** @var Agenda $candidate */
                $this->addEvent($candidate);
            }
        }
    }

    /**
     * @param Agenda $agenda
     *
     * @return Place[]
     */
    public function getPlaceEchantillons(Agenda $agenda)
    {
        if ($agenda->getPlace()) {
            $place = $this->searchPlaceByExternalId($agenda->getPlace()->getExternalId());

            if ($place) {
                return [$place];
            }
        }

        if ($agenda->getPlace() && $agenda->getPlace()->getCity()) {
            return $this->cityPlaces[$agenda->getPlace()->getCity()->getId()] ?? [];
        } elseif ($agenda->getPlace() && $agenda->getPlace()->getCountry()) {
            return $this->countryPlaces[$agenda->getPlace()->getCountry()->getId()] ?? [];
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

    public function getEventEchantillons(Agenda $event)
    {
        if ($event->getId() || $event->getUser()) {
            return [];
        }

        if ($event->getExternalId()) {
            return isset($this->agendas[$event->getExternalId()]) ? [$this->agendas[$event->getExternalId()]] : [];
        }

        return [];
    }

    private function addEvent(Agenda $event)
    {
        if ($event->getExternalId()) {
            $this->agendas[$event->getExternalId()] = $event;
        }
    }

    public function addNewEvent(Agenda $event)
    {
        $this->addEvent($event);
        if ($event->getPlace()) {
            $this->addPlace($event->getPlace());
        }
    }

    private function addPlace(Place $place)
    {
        $key = $place->getId() ?: spl_object_hash($place);

        if ($place->getCity()) {
            $this->cityPlaces[$place->getCity()->getId()][$key] = $place;
        } elseif ($place->getCountry()) {
            $this->countryPlaces[$place->getCountry()->getId()][$key] = $place;
        }
    }
}
