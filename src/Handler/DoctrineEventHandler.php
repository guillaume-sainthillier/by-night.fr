<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 04/03/2016
 * Time: 19:16.
 */

namespace App\Handler;

use App\Entity\Agenda;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Exploration;
use App\Entity\Place;
use App\Entity\Site;
use App\Entity\ZipCity;
use App\Geocoder\PlaceGeocoder;
use App\Reject\Reject;
use App\Repository\AgendaRepository;
use App\Repository\CityRepository;
use App\Repository\PlaceRepository;
use App\Repository\ZipCityRepository;
use App\Utils\Firewall;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class DoctrineEventHandler
{
    const BATCH_SIZE = 50;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var AgendaRepository
     */
    private $repoAgenda;

    /**
     * @var PlaceRepository
     */
    private $repoPlace;

    /**
     * @var CityRepository
     */
    private $repoCity;

    /**
     * @var ZipCityRepository
     */
    private $repoZipCity;

    /**
     * @var EventHandler
     */
    private $handler;

    /**
     * @var Firewall
     */
    private $firewall;

    /**
     * @var EchantillonHandler
     */
    private $echantillonHandler;

    /**
     * @var ExplorationHandler
     */
    private $explorationHandler;

    /**
     * @var PlaceGeocoder
     */
    private $geocoder;

    public function __construct(EntityManagerInterface $em, EventHandler $handler, Firewall $firewall, EchantillonHandler $echantillonHandler, PlaceGeocoder $geocoder)
    {
        $this->em = $em;
        $this->repoAgenda = $em->getRepository(Agenda::class);
        $this->repoPlace = $em->getRepository(Place::class);
        $this->repoCity = $em->getRepository(City::class);
        $this->repoZipCity = $em->getRepository(ZipCity::class);
        $this->handler = $handler;
        $this->firewall = $firewall;
        $this->echantillonHandler = $echantillonHandler;
        $this->geocoder = $geocoder;
        $this->explorationHandler = new ExplorationHandler();
    }

    /**
     * @return ExplorationHandler
     */
    public function getExplorationHandler()
    {
        return $this->explorationHandler;
    }

    /**
     * @param Agenda $event
     *
     * @return Agenda
     */
    public function handleOne(Agenda $event)
    {
        return $this->handleMany([$event])[0];
    }

    private function pingConnection()
    {
        if (false === $this->em->getConnection()->ping()) {
            $this->em->getConnection()->close();
            $this->em->getConnection()->connect();
        }
    }

    /**
     * @param Agenda[] $events
     *
     * @return Agenda[]
     */
    public function handleManyCLI(array $events)
    {
        $this->explorationHandler->start();
        $events = $this->handleMany($events);

        $historique = $this->explorationHandler->stop();
        $this->em->persist($historique);
        $this->em->flush();

        Monitor::writeln('');
        Monitor::displayStats();
        Monitor::displayTable([
            'NEWS' => $this->explorationHandler->getNbInserts(),
            'UPDATES' => $this->explorationHandler->getNbUpdates(),
            'BLACKLISTS' => $this->explorationHandler->getNbBlackLists(),
            'EXPLORATIONS' => $this->explorationHandler->getNbExplorations(),
        ]);

        return $events;
    }

    /**
     * @param Agenda[] $events
     *
     * @return Agenda[]
     */
    public function handleMany(array $events)
    {
        if (!\count($events)) {
            return [];
        }

        //On récupère toutes les explorations existantes pour ces événements
        $this->loadExplorations($events);

        //Grace à ça, on peut déjà filtrer une bonne partie des événements
        $this->doFilterAndClean($events);

        //On met ensuite à jour le statut de ces explorations en base
        $this->flushExplorations();

        $allowedEvents = $this->getAllowedEvents($events);
        $notAllowedEvents = $this->getNotAllowedEvents($events);
        unset($events);

        if ($this->explorationHandler->isStarted()) {
            $nbNotAllowedEvents = \count($notAllowedEvents);
            for ($i = 0; $i < $nbNotAllowedEvents; ++$i) {
                $this->explorationHandler->addBlackList();
            }
        }

        return $notAllowedEvents + $this->mergeWithDatabase($allowedEvents);
    }

    public function handleIdsToMigrate(array $ids)
    {
        if (!\count($ids)) {
            return;
        }

        $eventOwners = $this->repoAgenda->findBy([
            'facebookOwnerId' => \array_keys($ids),
        ]);

        $events = $this->repoAgenda->findBy([
            'facebookEventId' => \array_keys($ids),
        ]);

        $events = \array_merge($events, $eventOwners);
        foreach ($events as $event) {
            /** @var Agenda $event */
            if (isset($ids[$event->getFacebookEventId()])) {
                $event->setFacebookEventId($ids[$event->getFacebookEventId()]);
            }

            if (isset($ids[$event->getFacebookOwnerId()])) {
                $event->setFacebookOwnerId($ids[$event->getFacebookOwnerId()]);
            }
            $this->em->persist($event);
        }

        $places = $this->repoPlace->findBy([
            'facebookId' => \array_keys($ids),
        ]);

        foreach ($places as $place) {
            /** @var Place $place */
            if (isset($ids[$place->getFacebookId()])) {
                $place
                    ->setFacebookId($ids[$place->getFacebookId()])
                    ->setExternalId('FB-' . $ids[$place->getFacebookId()]);
            }
            $this->em->persist($place);
        }

        $this->em->flush();
    }

    /**
     * @param Agenda[] $events
     *
     * @return Agenda[]
     */
    private function getAllowedEvents(array $events)
    {
        return \array_filter($events, [$this->firewall, 'isValid']);
    }

    /**
     * @param Agenda[] $events
     *
     * @return Agenda[]
     */
    private function getNotAllowedEvents(array $events)
    {
        return \array_filter($events, function ($event) {
            return !$this->firewall->isValid($event);
        });
    }

    /**
     * @param Agenda[] $events
     *
     * @return array
     */
    private function getChunks(array $events)
    {
        $chunks = [];
        foreach ($events as $i => $event) {
            $key = 'city.' . $event->getPlace()->getCity()->getId();
            $chunks[$key][$i] = $event;
        }

        foreach ($chunks as $i => $chunk) {
            $chunks[$i] = \array_chunk($chunk, self::BATCH_SIZE, true);
        }

        return $chunks;
    }

    /**
     * @param array $chunks
     *
     * @return Agenda[]
     */
    private function unChunk(array $chunks)
    {
        $flat = [];
        foreach ($chunks as $chunk) {
            $flat = \array_merge($flat, $chunk);
        }

        return $flat;
    }

    /**
     * @param Agenda[] $events
     *
     * @return Agenda[]
     */
    private function mergeWithDatabase(array $events)
    {
        Monitor::createProgressBar(\count($events));

        $chunks = $this->getChunks($events);

        //Par localisation
        foreach ($chunks as $chunk) {
            $this->echantillonHandler->prefetchPlaceEchantillons($this->unChunk($chunk));

            //Par n événements
            foreach ($chunk as $currentEvents) {
                //Par événement
                foreach ($currentEvents as $i => $event) {
                    $this->echantillonHandler->prefetchEventEchantillons($event);

                    /**
                     * @var Agenda $event
                     */
                    $echantillonPlaces = $this->echantillonHandler->getPlaceEchantillons($event->getPlace());
                    $echantillonEvents = $this->echantillonHandler->getEventEchantillons($event);

                    //$oldUser = $event->getUser();
                    $event = $this->handler->handle($echantillonEvents, $echantillonPlaces, $event);
                    //$this->firewall->filterEventIntegrity($event, $oldUser);
                    if (!$this->firewall->isValid($event)) {
                        $this->explorationHandler->addBlackList();
                    } else {
                        $event = $this->em->merge($event);
                        $this->echantillonHandler->addNewEvent($event);
                        if ($this->firewall->isPersisted($event)) {
                            $this->explorationHandler->addUpdate();
                        } else {
                            $this->explorationHandler->addInsert();
                        }
                    }
                    Monitor::advanceProgressBar();
                    $events[$i] = $event;
                    $this->echantillonHandler->clearEvents();
                }
                $this->commit();
                $this->clearEvents();
                $this->firewall->deleteCache();
            }
            $this->clearPlaces();
        }
        Monitor::finishProgressBar();

        return $events;
    }

    private function clearPlaces()
    {
        $this->em->clear(Place::class);
        $this->em->clear(Site::class);
        $this->echantillonHandler->clearPlaces();
    }

    private function clearEvents()
    {
        $this->em->clear(Agenda::class);
        $this->echantillonHandler->clearEvents();
    }

    private function commit()
    {
        try {
            $this->em->flush();
        } catch (Exception $e) {
            Monitor::writeln(\sprintf(
                '<error>%s</error>',
                $e->getMessage()
            ));
        }
    }

    private function loadExplorations(array $events)
    {
        $ids = $this->getExplorationsIds($events);

        if (\count($ids)) {
            $this->firewall->loadExplorations($ids);
        }
    }

    private function flushExplorations()
    {
        $this->pingConnection();
        $explorations = $this->firewall->getExplorations();

        $batchSize = 500;
        $nbBatches = \ceil(\count($explorations) / $batchSize);

        for ($i = 0; $i < $nbBatches; ++$i) {
            $currentExplorations = \array_slice($explorations, $i * $batchSize, $batchSize);
            foreach ($currentExplorations as $exploration) {
                /**
                 * @var Exploration $exploration
                 */
                $exploration->setReason($exploration->getReject()->getReason());
                $this->explorationHandler->addExploration();
                $this->em->persist($exploration);
            }
            $this->em->flush();
        }
        $this->em->clear(Exploration::class);
        $this->firewall->flushExplorations();
    }

    /**
     * @param Agenda[] $events
     */
    private function doFilterAndClean(array $events)
    {
        foreach ($events as $event) {
            $event->setReject(new Reject());

            if ($event->getPlace()) {
                $event->getPlace()->setReject(new Reject());
            }

            if ($event->getFacebookEventId()) {
                $exploration = $this->firewall->getExploration($event->getFacebookEventId());

                //Une exploration a déjà eu lieu
                if ($exploration) {
                    $this->firewall->filterEventExploration($exploration, $event);
                    $reject = $exploration->getReject();

                    //Celle-ci a déjà conduit à l'élimination de l'événement
                    if (!$reject->isValid()) {
                        $event->getReject()->setReason($reject->getReason());

                        continue;
                    }
                }
            }

            //Même algorithme pour le lieu
            if ($event->getPlace() && $event->getPlace()->getExternalId()) {
                $exploration = $this->firewall->getExploration($event->getPlace()->getExternalId());

                if ($exploration && !$this->firewall->hasPlaceToBeUpdated($exploration) && !$exploration->getReject()->isValid()) {
                    $event->getReject()->addReason($exploration->getReject()->getReason());
                    $event->getPlace()->getReject()->setReason($exploration->getReject()->getReason());

                    continue;
                }
            }

            $this->firewall->filterEvent($event);
            if ($this->firewall->isValid($event)) {
                $this->guessEventLocation($event->getPlace());
                $this->firewall->filterEventLocation($event);
                $this->handler->cleanEvent($event);
            }
        }
    }

    private function guessEventCity(Place $place)
    {
        //Recherche du pays en premier lieu
        if ($place->getCountryName() && (!$place->getCountry() || $place->getCountry()->getName() !== $place->getCountryName())) {
            $country = $this->em->getRepository(Country::class)->findByName($place->getCountryName());
            $place->setCountry($country);
        }

        //Pas de pays détecté -> next
        if (!$place->getCountry()) {
            if ($place->getCountryName()) {
                $place->getReject()->addReason(Reject::BAD_COUNTRY);
            } else {
                $place->getReject()->addReason(Reject::NO_COUNTRY_PROVIDED);
            }

            return;
        }

        if (!$place->getCodePostal() && !$place->getVille()) {
            $place->getReject()->addReason(Reject::NO_PLACE_LOCATION_PROVIDED);

            return;
        }

        //Location fournie -> Vérification dans la base des villes existantes
        $zipCity = null;
        $city = null;

        //Ville + CP
        if ($place->getVille() && $place->getCodePostal()) {
            $zipCity = $this->repoZipCity->findByPostalCodeAndCity($place->getCodePostal(), $place->getVille(), $place->getCountry()->getId());
        }

        //Ville
        if (!$zipCity && $place->getVille()) {
            $zipCities = $this->repoZipCity->findByCity($place->getVille(), $place->getCountry()->getId());
            if (0 === \count($zipCities)) {
                $place->getReject()->addReason(Reject::BAD_PLACE_CITY_NAME);
            } elseif (\count($zipCities) > 1) {
                $place->getReject()->addReason(Reject::AMBIGOUS_CITY);
            } else {
                $zipCity = $zipCities[0];
            }
        }

        //CP
        if (!$zipCity && !$place->getCodePostal() && $place->getCodePostal()) {
            $zipCities = $this->repoZipCity->findByPostalCode($place->getCodePostal(), $place->getCountry()->getId());
            if (0 === \count($zipCities)) {
                $place->getReject()->addReason(Reject::BAD_PLACE_CITY_POSTAL_CODE);
            } elseif (\count($zipCities) > 1) {
                $place->getReject()->addReason(Reject::AMBIGOUS_ZIP);
            } else {
                $zipCity = $zipCities[0];
            }
        }

        if ($zipCity) {
            $city = $zipCity->getParent();
        }

        //Recherche de l'entité via sa ville ou son nom
        if (!$city) {
            $tries = \array_filter([$place->getVille(), $place->getNom()]);
            foreach ($tries as $try) {
                $cities = $this->repoCity->findByName($try, $place->getCountry()->getId());
                if (1 === \count($cities)) {
                    $city = $cities[0];

                    break;
                }
            }
        }

        if ($city || $zipCity) {
            $place->getReject()->setValid();
        }

        $place->setCity($city)->setZipCity($zipCity);
        if ($city) {
            $place->setCountry($city->getCountry());
        } elseif ($zipCity) {
            $place->setCountry($zipCity->getCountry());
        }
    }


    private function doUpgrade(Place $place)
    {
        $zipCity = null;
        $city = null;

        //Ville + CP
        if ($place->getVille() && $place->getCodePostal()) {
            $zipCity = $this->repoZipCity->findByPostalCodeAndCity($place->getCodePostal(), $place->getVille());
        }

        if (!$zipCity && $place->getVille()) {
            $zipCities = $this->repoZipCity->findByCity($place->getVille());
            if (0 === \count($zipCities)) {
                $place->getReject()->addReason(Reject::BAD_PLACE_CITY_NAME);
            } elseif (\count($zipCities) > 1) {
                $place->getReject()->addReason(Reject::AMBIGOUS_CITY);
            } else {
                $zipCity = $zipCities[0];
            }
        }

        if (!$zipCity && $place->getCodePostal()) {
            $zipCities = $this->repoZipCity->findByPostalCode($place->getCodePostal());
            if (0 === \count($zipCities)) {
                $place->getReject()->addReason(Reject::BAD_PLACE_CITY_POSTAL_CODE);
            } elseif (\count($zipCities) > 1) {
                $place->getReject()->addReason(Reject::AMBIGOUS_ZIP);
            } else {
                $zipCity = $zipCities[0];
            }
        }

        if ($zipCity) {
            $city = $zipCity->getParent();
        }

        //Recherche de l'entité via sa ville ou son nom
        if (!$city) {
            $tries = \array_filter([$place->getVille(), $place->getNom()]);
            foreach ($tries as $try) {
                $cities = $this->repoCity->findByName($try);
                if (1 === \count($cities)) {
                    $city = $cities[0];

                    break;
                }
            }
        }

        if ($city || $zipCity) {
            $place->getReject()->setValid();
        }

        $place->setCity($city)->setZipCity($zipCity);
        if ($city) {
            $place->setCountry($city->getCountry());
        } elseif ($zipCity) {
            $place->setCountry($zipCity->getCountry());
        }//On trouve la ville via le nom du lieu

    }

    public function upgrade(Place $place)
    {
        $this->doUpgrade($place);
        if ($place->getCity()) {
            return;
        }

        if ($place->getNom()) {
            $this->geocoder->geocodePlace($place);
        } elseif ($place->getLatitude() && $place->getLongitude()) {
            //Sinon, on tente de trouver la ville via lat/long
            $this->geocoder->geocodeCoordinates($place);
        }

        $this->doUpgrade($place);
    }

    public function guessEventLocation(Place $place)
    {
        //Pas besoin de trouver un lieu déjà blacklisté
        if ($place->getReject() && !$place->getReject()->isValid()) {
            return;
        }

        //On tente d'abord de trouver la ville via pays/cp/nom
        if ($place->getCountry() && ($place->getCodePostal() || $place->getVille())) {
            $this->guessEventCity($place);
            if ($place->getCity()) {
                return;
            }
        }

        //On trouve la ville via le nom du lieu
        if ($place->getNom()) {
            $this->geocoder->geocodePlace($place);
        } elseif ($place->getLatitude() && $place->getLongitude()) {
            //Sinon, on tente de trouver la ville via lat/long
            $this->geocoder->geocodeCoordinates($place);
        }

        $this->guessEventCity($place);
    }

    /**
     * @param Agenda[] $events
     *
     * @return int[]
     */
    private function getExplorationsIds(array $events)
    {
        $ids = [];
        foreach ($events as $event) {
            if ($event->getExternalId()) {
                $ids[$event->getExternalId()] = true;
            }

            if ($event->getPlace() && $event->getPlace()->getExternalId()) {
                $ids[$event->getPlace()->getExternalId()] = true;
            }
        }

        return \array_keys($ids);
    }
}
