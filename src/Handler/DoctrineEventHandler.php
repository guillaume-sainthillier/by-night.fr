<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 04/03/2016
 * Time: 19:16.
 */

namespace App\Handler;

use App\Entity\Event;
use App\Entity\City;
use App\Entity\Country;
use App\Entity\Exploration;
use App\Entity\Place;
use App\Entity\Site;
use App\Entity\ZipCity;
use App\Geocoder\PlaceGeocoder;
use App\Reject\Reject;
use App\Repository\EventRepository;
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
     * @var EventRepository
     */
    private $repoEvent;

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
        $this->repoEvent = $em->getRepository(Event::class);
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
     * @param Event $event
     *
     * @return Event
     */
    public function handleOne(Event $event, bool $flush = true)
    {
        return $this->handleMany([$event], $flush)[0];
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    public function handleManyCLI(array $events, bool $flush = true)
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

        $this->explorationHandler->reset();

        return $events;
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    public function handleMany(array $events, bool $flush = true)
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
        $events = null; // Call GC
        unset($events);

        foreach ($notAllowedEvents as $notAllowedEvent) {
            if ($notAllowedEvent->getId()) {
                $this->em->detach($notAllowedEvent);
            }
        }

        if ($this->explorationHandler->isStarted()) {
            $nbNotAllowedEvents = \count($notAllowedEvents);
            for ($i = 0; $i < $nbNotAllowedEvents; ++$i) {
                $this->explorationHandler->addBlackList();
            }
        }

        return $notAllowedEvents + $this->mergeWithDatabase($allowedEvents, $flush);
    }

    public function handleIdsToMigrate(array $ids)
    {
        if (!\count($ids)) {
            return;
        }

        $eventOwners = $this->repoEvent->findBy([
            'facebookOwnerId' => \array_keys($ids),
        ]);

        $events = $this->repoEvent->findBy([
            'facebookEventId' => \array_keys($ids),
        ]);

        $events = \array_merge($events, $eventOwners);
        foreach ($events as $event) {
            /** @var Event $event */
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
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function getAllowedEvents(array $events)
    {
        return \array_filter($events, [$this->firewall, 'isValid']);
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function getNotAllowedEvents(array $events)
    {
        return \array_filter($events, function ($event) {
            return !$this->firewall->isValid($event);
        });
    }

    /**
     * @param Event[] $events
     *
     * @return array
     */
    private function getChunks(array $events)
    {
        $chunks = [];
        foreach ($events as $i => $event) {
            if ($event->getPlace() && $event->getPlace()->getCity()) {
                $key = 'city.' . $event->getPlace()->getCity()->getId();
            } elseif ($event->getPlace() && $event->getPlace()->getCountry()) {
                $key = 'country.' . $event->getPlace()->getCountry()->getId();
            } else {
                $key = 'unknown';
            }

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
     * @return Event[]
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
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function mergeWithDatabase(array $events, bool $flush)
    {
        Monitor::createProgressBar(\count($events));

        $chunks = $this->getChunks($events);

        //Par localisation
        foreach ($chunks as $chunk) {
            $this->echantillonHandler->prefetchPlaceEchantillons($this->unChunk($chunk));

            //Par n événements
            foreach ($chunk as $currentEvents) {
                $this->echantillonHandler->prefetchEventEchantillons($currentEvents);

                //Par événement
                foreach ($currentEvents as $i => $event) {
                    /** @var Event $event */
                    $echantillonPlaces = $this->echantillonHandler->getPlaceEchantillons($event);
                    $echantillonEvents = $this->echantillonHandler->getEventEchantillons($event);

                    $url = $event->getUrl();
                    $event = $this->handler->handle($echantillonEvents, $echantillonPlaces, $event);
                    if (!$this->firewall->isValid($event)) {
                        $this->explorationHandler->addBlackList();
                    } else {
                        //Image URL has changed or never download
                        if ($event->getUrl() && !($event->getSystemPath() || $event->getUrl() !== $url)) {
                            $this->handler->handleDownload($event);
                        }

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
                }

                if ($flush) {
                    $this->commit();
                    $this->clearEvents();
                }
            }

            if ($flush) {
                $this->clearPlaces();
            }
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
        $this->em->clear(Event::class);
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
     * @param Event[] $events
     */
    private function doFilterAndClean(array $events)
    {
        foreach ($events as $event) {
            $event->setReject(new Reject())->setPlaceReject(new Reject());

            $place = new Place();
            $place
                ->setNom($event->getPlaceName())
                ->setRue($event->getPlaceStreet())
                ->setVille($event->getPlaceCity())
                ->setCodePostal($event->getPlacePostalCode())
                ->setExternalId($event->getPlaceExternalId())
                ->setCountryName($event->getPlaceCountryName())
                ->setCountry($event->getPlaceCountry())
                ->setReject(new Reject());
            $event->setPlace($place);

            if ($event->getExternalId()) {
                $exploration = $this->firewall->getExploration($event->getExternalId());

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
            if ($event->getPlaceExternalId()) {
                $exploration = $this->firewall->getExploration($event->getPlaceExternalId());

                if ($exploration && !$this->firewall->hasPlaceToBeUpdated($exploration) && !$exploration->getReject()->isValid()) {
                    $event->getReject()->addReason($exploration->getReject()->getReason());
                    $event->getPlaceReject()->setReason($exploration->getReject()->getReason());

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
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        //CP
        if (!$zipCity && $place->getCodePostal()) {
            $zipCities = $this->repoZipCity->findByPostalCode($place->getCodePostal(), $place->getCountry()->getId());
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        if ($zipCity) {
            $city = $zipCity->getParent();
        }


        //City
        if (!$city && $place->getVille()) {
            $cities = $this->repoCity->findByName($place->getVille(), $place->getCountry()->getId());
            if (1 === \count($cities)) {
                $city = $cities[0];
            }
        }

        $place->setCity($city)->setZipCity($zipCity);
        if ($city) {
            $place->setCountry($city->getCountry());
        } elseif ($zipCity) {
            $place->setCountry($zipCity->getCountry());
        }

        if ($place->getCity()) {
            $place->getReject()->setReason(Reject::VALID);
        }
    }

    private function guessEventCityUpgrade(Place $place)
    {
        if ($place->getCountryName() && (!$place->getCountry() || $place->getCountry()->getName() !== $place->getCountryName())) {
            $country = $this->em->getRepository(Country::class)->findByName($place->getCountryName());
            $place->setCountry($country);
        }

        //Location fournie -> Vérification dans la base des villes existantes
        $zipCity = null;
        $city = null;

        //Zip by city and postal code
        if ($place->getVille() && $place->getCodePostal()) {
            $zipCity = $this->repoZipCity->findByPostalCodeAndCity($place->getCodePostal(), $place->getVille());
        }

        //Zip by city
        if (!$zipCity && $place->getVille()) {
            $zipCities = $this->repoZipCity->findByCity($place->getVille());
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        //Zip by postal
        if (!$zipCity && $place->getCodePostal()) {
            $zipCities = $this->repoZipCity->findByPostalCode($place->getCodePostal());
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        if ($zipCity) {
            $city = $zipCity->getParent();
        }

        //City by city
        if (!$city && $place->getVille()) {
            $cities = $this->repoCity->findByName($place->getVille());
            if (1 === \count($cities)) {
                $city = $cities[0];
            }
        }

        //City by place name
        if (!$city && $place->getNom()) {
            $cities = $this->repoCity->findByName($place->getNom());
            if (1 === \count($cities)) {
                $city = $cities[0];
            }
        }

        $place->setCity($city)->setZipCity($zipCity);
        if ($city) {
            $place->setCountry($city->getCountry());
        } elseif ($zipCity) {
            $place->setCountry($zipCity->getCountry());
        }

        if ($place->getCity()) {
            $place->getReject()->setReason(Reject::VALID);
        }
    }

    public function guessEventLocation(Place $place)
    {
        //Pas besoin de trouver un lieu déjà blacklisté
        if (!$place->getReject()->isValid()) {
            return;
        }

        $this->guessEventCity($place);
    }

    /**
     * @param Event[] $events
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

            if ($event->getPlaceExternalId()) {
                $ids[$event->getPlaceExternalId()] = true;
            }
        }

        return \array_keys($ids);
    }
}
