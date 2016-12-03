<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 01/12/2016
 * Time: 20:22
 */

namespace TBN\MajDataBundle\Utils;


use Doctrine\ORM\EntityManagerInterface;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Place;
use TBN\MainBundle\Entity\Site;

class EchantillonHandler
{
    private $repoAgenda;
    private $repoPlace;

    private $places;
    private $fbPlaces;
    private $newPlaces;

    private $agendas;
    private $fbAgendas;
    private $newAgendas;

    public function __construct(EntityManagerInterface $em) {
        $this->repoAgenda = $em->getRepository('TBNAgendaBundle:Agenda');
        $this->repoPlace = $em->getRepository('TBNAgendaBundle:Place');

        $this->init();
    }

    protected function initEvents() {
        $this->agendas = [];
        $this->fbAgendas = [];
        $this->newAgendas = [];
    }

    protected function initPlaces() {
        $this->places = [];
        $this->fbPlaces = [];
        $this->newPlaces = [];
    }

    protected function init() {
        $this->initEvents();
        $this->initPlaces();
    }

    public function flushEvents() {
        unset($this->agendas, $this->newAgendas, $this->fbAgendas);
        $this->initEvents();
    }

    public function flushPlaces() {
        unset($this->fbPlaces, $this->newPlaces, $this->places);
        $this->initPlaces();
    }

    public function prefetchPlaceEchantillons(array $events) {
        $places = array_map(function(Agenda $event) {
            return $event->getPlace();
        }, $events);

        $byFbIdPlaces = [];
        $bySitePlaces = [];

        foreach($places as $place) {
            /**
             * @var Place $place
             */
            if($place->getFacebookId()) {
                $byFbIdPlaces[$place->getFacebookId()] = $place->getFacebookId();
            }
        }

        if(count($byFbIdPlaces) > 0) {
            $fbPlaces = $this->repoPlace->findBy([
                'facebookId' => $byFbIdPlaces
            ]);

            foreach($fbPlaces as $place) {
                $this->addFbPlace($place);
            }
        }

        foreach($places as $place) {
            if(! $place->getFacebookId() || !isset($this->fbPlaces[$place->getFacebookId()])) {
                $key = $place->getSite()->getId();
                $bySitePlaces[$key] = $key;
            }
        }

        if(count($bySitePlaces)) {
            $sitePlaces = $this->repoPlace->findBy([
                'site' => $bySitePlaces
            ]);

            foreach($sitePlaces as $place) {
                $this->addPlace($place);
            }
        }
    }

    public function prefetchEventEchantillons(array $events) {
        $byFbIdEvents = [];
        $byDateEvents = [];
        foreach($events as $event) {
            /**
             * @var Agenda $event
             */
            if($event->getFacebookEventId()) {
                $byFbIdEvents[$event->getFacebookEventId()] = $event->getFacebookEventId();
            }
        }

        if(count($byFbIdEvents) > 0) {
            $fbEvents = $this->repoAgenda->findBy([
                'facebookEventId' => $byFbIdEvents
            ]);

            foreach($fbEvents as $event) {
                $this->addFbEvent($event);
            }
        }

        foreach($events as $event) {
            if(! $event->getFacebookEventId() || !isset($this->fbAgendas[$event->getFacebookEventId()])) {
                $key = $this->getAgendaCacheKey($event);
                $byDateEvents[$key] = $event;
            }
        }

        if(count($byDateEvents)) {
            $dateEvents = $this->repoAgenda->findAllByDates($byDateEvents);
            foreach($dateEvents as $event) {
                $this->addEvent($event);
            }
        }
    }

    public function getPlaceEchantillons(Place $place) {
        if($place->getFacebookId() && isset($this->fbPlaces[$place->getFacebookId()])) {
            return [$this->fbPlaces[$place->getFacebookId()]];
        }

        return array_merge(
            $this->getPlaces($place),
            $this->getNewPlaces($place)
        );
    }

    public function getEventEchantillons(Agenda $event) {
        if($event->getFacebookEventId() && isset($this->fbAgendas[$event->getFacebookEventId()])) {
            return [$this->fbAgendas[$event->getFacebookEventId()]];
        }

        return array_merge(
            $this->getEvents($event),
            $this->getNewEvents($event)
        );
    }

    protected function addFbEvent(Agenda $event) {
        $this->fbAgendas[$event->getFacebookEventId()] = $event;
    }

    protected function addEvent(Agenda $event) {
        $key = $this->getAgendaCacheKey($event);
        if(! isset($this->agendas[$key])) {
            $this->agendas[$key] = [];
        }
        $this->agendas[$key][] = $event;
    }

    protected function getEvents(Agenda $event) {
        $key = $this->getAgendaCacheKey($event);
        if(! isset($this->agendas[$key])) {
            return [];
        }

        return $this->agendas[$key];

    }

    public function addNewEvent(Agenda $event) {
        if($event->getFacebookEventId()) {
            $this->fbAgendas[$event->getFacebookEventId()] = $event;
        }

        $key = $this->getAgendaCacheKey($event);
        if(! isset($this->newAgendas[$key])) {
            $this->newAgendas[$key] = [];
        }

        $id = spl_object_hash($event);
        $this->newAgendas[$key][$id] = $event;

        $this->addNewPlace($event->getPlace());
    }

    protected function getNewEvents(Agenda $event) {
        $key = $this->getAgendaCacheKey($event);
        if(! isset($this->newAgendas[$key])) {
            return [];
        }

        return $this->newAgendas[$key];
    }

    protected function addFbPlace(Place $place) {
        $this->fbPlaces[$place->getFacebookId()] = $place;
    }

    protected function addPlace(Place $place) {
        $key = $place->getSite()->getId();
        if(! isset($this->places[$key])) {
            $this->places[$key] = [];
        }
        $this->places[$key][] = $place;
    }

    protected function getPlaces(Place $place) {
        $key = $place->getSite()->getId();
        if(! isset($this->places[$key])) {
            return [];
        }

        return $this->places[$key];
    }

    public function addNewPlace(Place $place) {
        if($place->getFacebookId()) {
            $this->fbPlaces[$place->getFacebookId()] = $place;
        }

        $key = $place->getSite()->getId();
        if(! isset($this->newPlaces[$key])) {
            $this->newPlaces[$key] = [];
        }

        $id = spl_object_hash($place);
        $this->newPlaces[$key][$id] = $place;
    }

    protected function getNewPlaces(Place $place) {
        $key = $place->getSite()->getId();
        if(isset($this->newPlaces[$key])) {
            return $this->newPlaces[$key];
        }

        return [];
    }

    protected function getAgendaCacheKey(Agenda $agenda)
    {
        return sprintf(
            "%s.%s.%s",
            $agenda->getSite()->getId(),
            $agenda->getDateDebut()->format('Y-m-d'),
            $agenda->getDateFin()->format('Y-m-d')
        );
    }
}