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
use App\Repository\AgendaRepository;
use App\Repository\PlaceRepository;
use Doctrine\ORM\EntityManagerInterface;

class EchantillonHandler
{
    /**
     * @var AgendaRepository
     */
    private $repoAgenda;

    /**
     * @var PlaceRepository
     */
    private $repoPlace;

    /**
     * @var Place[]
     */
    private $places;

    /**
     * @var Place[]
     */
    private $cityPlaces;

    /**
     * @var Place[]
     */
    private $newPlaces;

    /**
     * @var Agenda[]
     */
    private $agendas;

    /**
     * @var Agenda[]
     */
    private $fbAgendas;

    /**
     * @var Agenda[]
     */
    private $newAgendas;

    public function __construct(EntityManagerInterface $em)
    {
        $this->repoAgenda = $em->getRepository(Agenda::class);
        $this->repoPlace = $em->getRepository(Place::class);

        $this->init();
    }

    private function initEvents()
    {
        $this->agendas = [];
        $this->fbAgendas = [];
        $this->newAgendas = [];
    }

    private function initPlaces()
    {
        $this->places = [];
        $this->cityPlaces = [];
        $this->newPlaces = [];
    }

    private function init()
    {
        $this->initEvents();
        $this->initPlaces();
    }

    public function clearEvents()
    {
        unset($this->agendas, $this->newAgendas, $this->fbAgendas);
        $this->initEvents();
    }

    public function clearPlaces()
    {
        unset($this->places, $this->newPlaces, $this->fbPlaces);
        $this->initPlaces();
    }

    public function prefetchPlaceEchantillons(array $events)
    {
        $places = \array_map(function (Agenda $event) {
            return $event->getPlace();
        }, $events);

        unset($events);

        $withExternalIdPlaces = [];
        $withoutExternalIdPlaces = [];
        foreach ($places as $place) {
            /** @var Place $place */
            if ($place->getExternalId()) {
                $withExternalIdPlaces[$place->getExternalId()] = true;
            } else {
                $withoutExternalIdPlaces[$place->getCity()->getId()] = true;
            }
        }
        $withExternalIdPlaces = \array_keys($withExternalIdPlaces);
        $withoutExternalIdPlaces = \array_keys($withoutExternalIdPlaces);

        //On prend toutes les places déjà connues par leur external ID
        if (\count($withExternalIdPlaces) > 0) {
            $places = $this->repoPlace->findBy([
                'externalId' => $withExternalIdPlaces,
            ]);

            foreach ($places as $place) {
                $this->addPlace($place);
            }
        }

        //On prend ensuite toutes les places selon leur localisation
        if (\count($withoutExternalIdPlaces) > 0) {
            $places = $this->repoPlace->findBy([
                'city' => $withoutExternalIdPlaces,
                'externalId' => null
            ]);

            foreach ($places as $place) {
                $this->addPlace($place);
            }
        }
    }

    public function prefetchEventEchantillons(Agenda $event)
    {
        if ($event->getUser()) {
            return;
        }

        if (!$event->getExternalId()) {
            throw new \RuntimeException("Unable to find echantillon without an external ID");
        }

        /** @var Agenda $candidate */
        $candidate = $this->repoAgenda->findOneBy(['externalId' => $event->getExternalId()]);
        if (null !== $candidate) {
            $this->addEvent($candidate);
        }
    }

    /**
     * @param Place $place
     *
     * @return Place[]
     */
    public function getPlaceEchantillons(Place $place)
    {
        if ($place->getExternalId()) {
            return isset($this->places[$place->getExternalId()]) ? [$this->places[$place->getExternalId()]] : [];
        }

        return $this->cityPlaces[$place->getCity()->getId()] ?? [];
    }

    public function getEventEchantillons(Agenda $event)
    {
        if ($event->getUser()) {
            return [];
        }

        if ($event->getExternalId()) {
            return isset($this->agendas[$event->getExternalId()]) ? [$this->agendas[$event->getExternalId()]] : [];
        }

        return [];
    }

    private function addEvent(Agenda $event)
    {
        $this->agendas[$event->getExternalId()] = $event;
    }

    public function addNewEvent(Agenda $event)
    {
        $this->addEvent($event);
        $this->addPlace($event->getPlace());
    }

    private function addPlace(Place $place)
    {
        if ($place->getExternalId()) {
            $this->places[$place->getExternalId()] = $place;
        } else {
            $this->cityPlaces[$place->getCity()->getId()][$place->getId()] = $place;
        }
    }
}
