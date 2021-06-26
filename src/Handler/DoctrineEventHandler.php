<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Entity\Event;
use App\Entity\ParserData;
use App\Entity\Place;
use App\Reject\Reject;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use App\Repository\ZipCityRepository;
use App\Utils\Firewall;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class DoctrineEventHandler
{
    public const BATCH_SIZE = 50;

    private EntityManagerInterface $em;

    private CityRepository $repoCity;

    private ZipCityRepository $repoZipCity;

    private CountryRepository $countryRepository;

    private EventHandler $handler;

    private Firewall $firewall;

    private EchantillonHandler $echantillonHandler;

    private ParserHistoryHandler $parserHistoryHandler;

    public function __construct(EntityManagerInterface $em, EventHandler $handler, Firewall $firewall, EchantillonHandler $echantillonHandler, CityRepository $cityRepository, ZipCityRepository $zipCityRepository, CountryRepository $countryRepository)
    {
        $this->em = $em;
        $this->repoCity = $cityRepository;
        $this->repoZipCity = $zipCityRepository;
        $this->handler = $handler;
        $this->firewall = $firewall;
        $this->echantillonHandler = $echantillonHandler;
        $this->parserHistoryHandler = new ParserHistoryHandler();
        $this->countryRepository = $countryRepository;
    }

    /**
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
    public function handleMany(array $events, bool $flush = true)
    {
        if (0 === \count($events)) {
            return [];
        }

        //On récupère toutes les explorations existantes pour ces événements
        $this->loadParserDatas($events);

        //Grace à ça, on peut déjà filtrer une bonne partie des événements
        $this->doFilterAndClean($events);

        //On met ensuite à jour le statut de ces explorations en base
        $this->flushParserDatas();

        $allowedEvents = $this->getAllowedEvents($events);
        $notAllowedEvents = $this->getNotAllowedEvents($events);
        $events = null; // Call GC
        unset($events);

        foreach ($notAllowedEvents as $notAllowedEvent) {
            if ($notAllowedEvent->getId()) {
                $this->em->detach($notAllowedEvent);
            }
        }

        if ($this->parserHistoryHandler->isStarted()) {
            $nbNotAllowedEvents = \count($notAllowedEvents);
            for ($i = 0; $i < $nbNotAllowedEvents; ++$i) {
                $this->parserHistoryHandler->addBlackList();
            }
        }

        return $notAllowedEvents + $this->mergeWithDatabase($allowedEvents, $flush);
    }

    private function loadParserDatas(array $events): void
    {
        $ids = $this->getParserDataIds($events);

        if (\count($ids) > 0) {
            $this->firewall->loadParserDatas($ids);
        }
    }

    /**
     * @param Event[] $events
     *
     * @return (int|string)[]
     *
     * @psalm-return list<0|string>
     */
    private function getParserDataIds(array $events): array
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

        return array_keys($ids);
    }

    /**
     * @param Event[] $events
     */
    private function doFilterAndClean(array $events): void
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
                if (null !== $exploration) {
                    $this->firewall->filterEventExploration($exploration, $event);
                    $reject = $exploration->getReject();

                    //Celle-ci a déjà conduit à l'élimination de l'événement
                    if (false === $reject->isValid()) {
                        $event->getReject()->setReason($reject->getReason());

                        continue;
                    }
                }
            }

            //Même algorithme pour le lieu
            if ($event->getPlaceExternalId()) {
                $exploration = $this->firewall->getExploration($event->getPlaceExternalId());

                if ($exploration && !$this->firewall->hasPlaceToBeUpdated($exploration, $event) && !$exploration->getReject()->isValid()) {
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

    /**
     * @return void
     */
    public function guessEventLocation(Place $place)
    {
        //Pas besoin de trouver un lieu déjà blacklisté
        if (false === $place->getReject()->isValid()) {
            return;
        }

        $this->guessEventCity($place);
    }

    /**
     * @return void
     */
    private function guessEventCity(Place $place)
    {
        //Recherche du pays en premier lieu
        if ($place->getCountryName() && (!$place->getCountry() || $place->getCountry()->getName() !== $place->getCountryName())) {
            $country = $this->countryRepository->findByName($place->getCountryName());
            $place->setCountry($country);
        }

        //Pas de pays détecté -> next
        if (null === $place->getCountry()) {
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

        if (null !== $zipCity) {
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
        } elseif (null !== $zipCity) {
            $place->setCountry($zipCity->getCountry());
        }

        if (null !== $place->getCity()) {
            $place->getReject()->setReason(Reject::VALID);
        }
    }

    private function flushParserDatas(): void
    {
        $explorations = $this->firewall->getParserDatas();

        $batchSize = 500;
        $nbBatches = ceil(\count($explorations) / $batchSize);

        for ($i = 0; $i < $nbBatches; ++$i) {
            $currentExplorations = \array_slice($explorations, $i * $batchSize, $batchSize);
            /** @var ParserData $exploration */
            foreach ($currentExplorations as $exploration) {
                $exploration->setReason($exploration->getReject()->getReason());
                $this->parserHistoryHandler->addExploration();
                $this->em->persist($exploration);
            }
            $this->em->flush();
        }
        $this->em->clear(ParserData::class);
        $this->firewall->flushParserDatas();
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function getAllowedEvents(array $events)
    {
        return array_filter($events, fn (Event $event) => $this->firewall->isValid($event));
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function getNotAllowedEvents(array $events)
    {
        return array_filter($events, fn ($event) => !$this->firewall->isValid($event));
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function mergeWithDatabase(array $events, bool $flush)
    {
        if (0 === \count($events)) {
            return [];
        }

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
                        $this->parserHistoryHandler->addBlackList();
                    } else {
                        //Image URL has changed or never downloaded
                        if ($event->getUrl() && (!$event->getImageSystem()->getName() || $event->getUrl() !== $url)) {
                            $this->handler->handleDownload($event);
                        }

                        $this->em->persist($event);
                        $this->echantillonHandler->addNewEvent($event);
                        if (null !== $event->getId()) {
                            $this->parserHistoryHandler->addUpdate();
                        } else {
                            $this->parserHistoryHandler->addInsert();
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
            $chunks[$i] = array_chunk($chunk, self::BATCH_SIZE, true);
        }

        return $chunks;
    }

    /**
     * @return Event[]
     */
    private function unChunk(array $chunks)
    {
        $flat = [];
        foreach ($chunks as $chunk) {
            $flat = array_merge($flat, $chunk);
        }

        return $flat;
    }

    private function commit(): void
    {
        try {
            $this->em->flush();
        } catch (Exception $e) {
            Monitor::writeln(sprintf(
                '<error>%s</error>',
                $e->getMessage()
            ));
        }
    }

    private function clearEvents(): void
    {
        $this->em->clear(Event::class);
        $this->echantillonHandler->clearEvents();
    }

    private function clearPlaces(): void
    {
        $this->em->clear(Place::class);
        $this->echantillonHandler->clearPlaces();
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    public function handleManyCLI(array $events, bool $flush = true)
    {
        $this->parserHistoryHandler->start();
        $events = $this->handleMany($events, $flush);

        $parserHistory = $this->parserHistoryHandler->stop();
        $this->em->persist($parserHistory);
        $this->em->flush();

        Monitor::writeln('');
        Monitor::displayStats();
        Monitor::displayTable([
            'NEWS' => $this->parserHistoryHandler->getNbInserts(),
            'UPDATES' => $this->parserHistoryHandler->getNbUpdates(),
            'BLACKLISTS' => $this->parserHistoryHandler->getNbBlackLists(),
            'EXPLORATIONS' => $this->parserHistoryHandler->getNbExplorations(),
        ]);

        $this->parserHistoryHandler->reset();

        return $events;
    }
}
