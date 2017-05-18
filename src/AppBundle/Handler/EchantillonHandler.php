<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 01/12/2016
 * Time: 20:22
 */

namespace AppBundle\Handler;


use Doctrine\ORM\EntityManagerInterface;
use AppBundle\Entity\Agenda;
use AppBundle\Entity\Place;


class EchantillonHandler
{
    /**
     * @var \AppBundle\Repository\AgendaRepository
     */
    private $repoAgenda;

    /**
     * @var \AppBundle\Repository\PlaceRepository
     */
    private $repoPlace;

    /**
     * @var Place[]
     */
    private $places;

    /**
     * @var Place[]
     */
    private $fbPlaces;

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
        $this->repoAgenda = $em->getRepository('AppBundle:Agenda');
        $this->repoPlace = $em->getRepository('AppBundle:Place');

        $this->init();
    }

    protected function initEvents()
    {
        $this->agendas = [];
        $this->fbAgendas = [];
        $this->newAgendas = [];
    }

    protected function initPlaces()
    {
        $this->places = [];
        $this->fbPlaces = [];
        $this->newPlaces = [];
    }

    protected function init()
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
        $places = array_map(function (Agenda $event) {
            return $event->getPlace();
        }, $events);

        unset($events);

        $byFbIdPlaces = [];
        $byCityPlaces = [];
        $byZipCityPlaces = [];

        foreach ($places as $place) {
            /**
             * @var Place $place
             */
            if ($place->getFacebookId()) {
                $byFbIdPlaces[$place->getFacebookId()] = true;
            }
        }
        $byFbIdPlaces = array_keys($byFbIdPlaces);

        //On prend toutes les places déjà connues par leur ID FB
        if (count($byFbIdPlaces) > 0) {
            $fbPlaces = $this->repoPlace->findBy([
                'facebookId' => $byFbIdPlaces
            ]);

            foreach ($fbPlaces as $place) {
                $this->addFbPlace($place);
            }
        }

        //On prend ensuite toutes les places selon leur localisation
        foreach ($places as $place) {
            if($place->getCity()) {
                $key = $place->getCity()->getId();
                $byCityPlaces[$key] = true;
            }elseif($place->getZipCity()) {
                $key = $place->getZipCity()->getId();
                $byZipCityPlaces[$key] = true;
            }
        }

        $byCityPlaces = array_keys($byCityPlaces);
        $byZipCityPlaces = array_keys($byZipCityPlaces);

        $sitePlaces = [];
        if (count($byCityPlaces)) {
            $sitePlaces = $this->repoPlace->findByCities($byCityPlaces, $byFbIdPlaces);
        }elseif(count($byZipCityPlaces)) {
            $sitePlaces = $this->repoPlace->findByZipCities($byZipCityPlaces, $byFbIdPlaces);
        }

        foreach ($sitePlaces as $place) {
            $this->addPlace($place);
        }
    }

    public function prefetchEventEchantillons(array $events)
    {
        $byFbIdEvents = [];
        $byDateEvents = [];
        foreach ($events as $event) {
            /**
             * @var Agenda $event
             */
            if ($event->getFacebookEventId()) {
                $byFbIdEvents[$event->getFacebookEventId()] = true;
            }
        }
        $byFbIdEvents = array_keys($byFbIdEvents);

        if (count($byFbIdEvents) > 0) {
            $fbEvents = $this->repoAgenda->findBy([
                'facebookEventId' => $byFbIdEvents
            ]);

            foreach ($fbEvents as $event) {
                $this->addFbEvent($event);
            }
        }

        foreach ($events as $event) {
            if(!$event->getFacebookEventId()) {
                $key = $this->getAgendaCacheKey($event);
                $byDateEvents[$key] = $event;
            }
        }

        if (count($byDateEvents)) {
            $dateEvents = $this->repoAgenda->findAllByDates($byDateEvents, $byFbIdEvents);
            foreach ($dateEvents as $event) {
                $this->addEvent($event);
            }
        }
    }

    /**
     * @param Place $place
     * @return Place[]
     */
    public function getPlaceEchantillons(Place $place)
    {
        if ($place->getFacebookId() && isset($this->fbPlaces[$place->getFacebookId()])) {
            return [$this->fbPlaces[$place->getFacebookId()]];
        }

        return array_merge(
            $this->places,
            $this->newPlaces,
            $this->fbPlaces
        );
    }


    public function getEventEchantillons(Agenda $event)
    {
        if ($event->getFacebookEventId()) {
            if(isset($this->fbAgendas[$event->getFacebookEventId()])) {
                return [$this->fbAgendas[$event->getFacebookEventId()]];
            }

            //Pas d'ID fb trouvé -> Pas de chances pour trouver un doublon, on ajoute alors l'événement
            return [];
        }

        return array_merge(
            $this->agendas,
            $this->newAgendas,
            $this->fbAgendas
        );
    }

    protected function addFbEvent(Agenda $event)
    {
        $this->fbAgendas[$event->getFacebookEventId()] = $event;
    }

    protected function addEvent(Agenda $event)
    {
        $this->agendas[$event->getId()] = $event;
    }

    protected function getEvents(Agenda $event)
    {
        $key = $this->getAgendaCacheKey($event);
        if (!isset($this->agendas[$key])) {
            return [];
        }

        return $this->agendas[$key];

    }

    public function addNewEvent(Agenda $event)
    {
        if ($event->getFacebookEventId()) {
            $this->addFbEvent($event);
        }elseif ($event->getId()) {
            $this->agendas[$event->getId()] = $event;
        } else {
            $key = spl_object_hash($event);
            $this->newAgendas[$key] = $event;
        }

        $this->addNewPlace($event->getPlace());
    }

    protected function getNewEvents(Agenda $event)
    {
        $key = $this->getAgendaCacheKey($event);
        if (!isset($this->newAgendas[$key])) {
            return [];
        }

        return $this->newAgendas[$key];
    }

    protected function addFbPlace(Place $place)
    {
        $this->fbPlaces[$place->getFacebookId()] = $place;
    }

    protected function addPlace(Place $place)
    {
        $this->places[$place->getId()] = $place;
    }

    protected function getPlaces(Place $place)
    {
        $key = $place->getSite()->getId();
        if (!isset($this->places[$key])) {
            return [];
        }

        return $this->places[$key];
    }

    public function addNewPlace(Place $place)
    {
        if ($place->getFacebookId()) {
            $this->fbPlaces[$place->getFacebookId()] = $place;
        }elseif($place->getId()) {
            $this->places[$place->getId()] = $place;
        }else {
            $key = spl_object_hash($place);
            $this->newPlaces[$key] = $place;
        }
    }

    protected function getNewPlaces(Place $place)
    {
        $key = $place->getSite()->getId();
        if (isset($this->newPlaces[$key])) {
            return $this->newPlaces[$key];
        }

        return [];
    }

    protected function getAgendaCacheKey(Agenda $agenda)
    {
        return sprintf(
            "%s.%s",
            $agenda->getDateDebut()->format('Y-m-d'),
            $agenda->getDateFin()->format('Y-m-d')
        );
    }
}
