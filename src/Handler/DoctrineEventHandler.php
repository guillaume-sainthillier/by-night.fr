<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Contracts\DependencyCatalogueInterface;
use App\Contracts\DependencyObjectInterface;
use App\Contracts\DependencyProvidableInterface;
use App\Contracts\DependencyRequirableInterface;
use App\Contracts\DtoEntityIdentifierResolvableInterface;
use App\Dependency\DependencyCatalogue;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Entity\Event;
use App\Entity\ParserData;
use App\Entity\Place;
use App\Reject\Reject;
use App\Repository\CityRepository;
use App\Repository\CountryRepository;
use App\Repository\ZipCityRepository;
use App\Utils\ChunkUtils;
use App\Utils\Firewall;
use App\Utils\MemoryUtils;
use App\Utils\Monitor;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

class DoctrineEventHandler
{
    private const CHUNK_SIZE = 50;
    private ParserHistoryHandler $parserHistoryHandler;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private EventHandler $handler,
        private Firewall $firewall,
        private EntityProviderHandler $entityProviderHandler,
        private EchantillonHandler $echantillonHandler,
        private EntityFactoryHandler $entityFactoryHandler,
        private CityRepository $repoCity,
        private ZipCityRepository $repoZipCity,
        private CountryRepository $countryRepository
    ) {
        $this->parserHistoryHandler = new ParserHistoryHandler();
    }

    public function handleOne(EventDto $dto, bool $flush = true): Event
    {
        return $this->handleMany([$dto], $flush)[0];
    }

    /**
     * @param EventDto[] $dtos
     *
     * @return (null|object)[]
     *
     * @psalm-return array<null|object>
     */
    public function handleMany(array $dtos, bool $flush = true): array
    {
        if (0 === \count($dtos)) {
            return [];
        }

        return $this->mergeWithDatabase($dtos);

        // On récupère toutes les explorations existantes pour ces événements
        // $this->loadExternalIdsData($dtos);

        // Grace à ça, on peut déjà filtrer une bonne partie des événements
        // $this->doFilterAndClean($dtos);

        // On met ensuite à jour le statut de ces explorations en base
        // $this->flushParserDatas();

        // $allowedEvents = $this->getAllowedEvents($dtos);
        // $notAllowedEvents = $this->getNotAllowedEvents($dtos);
        // $dtos = null; // Call GC
        // unset($dtos);

        /*
        foreach ($notAllowedEvents as $notAllowedEvent) {
            if ($notAllowedEvent->getId()) {
                $this->entityManager->detach($notAllowedEvent);
            }
        }

        if ($this->parserHistoryHandler->isStarted()) {
            $nbNotAllowedEvents = \count($notAllowedEvents);
            for ($i = 0; $i < $nbNotAllowedEvents; ++$i) {
                $this->parserHistoryHandler->addBlackList();
            }
        }*/

        // return $notAllowedEvents + $this->mergeWithDatabase($allowedEvents, $flush);
    }

    /**
     * @param EventDto[] $dtos
     */
    private function loadExternalIdsData(array $dtos): void
    {
        $ids = $this->getAllExternalIds($dtos);

        if (\count($ids) > 0) {
            $this->firewall->loadExternalIdsData($ids);
        }
    }

    /**
     * @param EventDto[] $dtos
     *
     * @return (int|string)[]
     */
    private function getAllExternalIds(array $dtos): array
    {
        $ids = [];
        foreach ($dtos as $dto) {
            \assert($dto instanceof EventDto);
            if (null !== $dto->getExternalId()) {
                $ids[$dto->getExternalId()] = true;
            }

            if (null !== $dto->place && null !== $dto->place->getExternalId()) {
                $ids[$dto->place->getExternalId()] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param EventDto[] $dtos
     */
    private function doFilterAndClean(array $dtos): void
    {
        foreach ($dtos as $dto) {
            $dto->reject = new Reject();

            if ($dto->place) {
                $dto->place->reject = new Reject();
            }

            if (null !== $dto->getExternalId()) {
                $exploration = $this->firewall->getExploration($dto->getExternalId());

                // Une exploration a déjà eu lieu
                if (null !== $exploration) {
                    $this->firewall->filterEventExploration($exploration, $dto);
                    $reject = $exploration->getReject();

                    // Celle-ci a déjà conduit à l'élimination de l'événement
                    if (false === $reject->isValid()) {
                        $dto->reject->setReason($reject->getReason());

                        continue;
                    }
                }
            }

            // Même algorithme pour le lieu
            if (null !== $dto->place && null !== $dto->place->getExternalId()) {
                $exploration = $this->firewall->getExploration($dto->place->getExternalId());

                if ($exploration && !$this->firewall->hasPlaceToBeUpdated($exploration, $dto) && !$exploration->getReject()->isValid()) {
                    $dto->reject->addReason($exploration->getReject()->getReason());
                    $dto->place->reject->setReason($exploration->getReject()->getReason());

                    continue;
                }
            }

            $this->firewall->filterEvent($dto);
            if ($this->firewall->isEventDtoValid($dto)) {
                $this->guessEventLocation($dto->place);
                $this->firewall->filterEventLocation($dto);
                $this->handler->cleanEvent($dto);
            }
        }
    }

    public function guessEventLocation(PlaceDto $dto): void
    {
        // Pas besoin de trouver un lieu déjà blacklisté
        if (false === $dto->reject->isValid()) {
            return;
        }

        $this->guessPlaceCity($dto);
    }

    private function guessPlaceCity(PlaceDto $dto): void
    {
        // Recherche du pays en premier lieu
        if ($dto->getCountryName() && (!$dto->getCountry() || $dto->getCountry()->getName() !== $dto->getCountryName())) {
            $country = $this->countryRepository->findOneByName($dto->getCountryName());
            $dto->setCountry($country);
        }

        // Pas de pays détecté -> next
        if (null === $dto->getCountry()) {
            if ($dto->getCountryName()) {
                $dto->getReject()->addReason(Reject::BAD_COUNTRY);
            } else {
                $dto->getReject()->addReason(Reject::NO_COUNTRY_PROVIDED);
            }

            return;
        }

        if (!$dto->getCodePostal() && !$dto->getVille()) {
            return;
        }

        // Location fournie -> Vérification dans la base des villes existantes
        $zipCity = null;
        $city = null;

        // Ville + CP
        if ($dto->getVille() && $dto->getCodePostal()) {
            $zipCity = $this->repoZipCity->findOneByPostalCodeAndCity($dto->getCodePostal(), $dto->getVille(), $dto->getCountry()->getId());
        }

        // Ville
        if (!$zipCity && $dto->getVille()) {
            $zipCities = $this->repoZipCity->findAllByCity($dto->getVille(), $dto->getCountry()->getId());
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        // CP
        if (!$zipCity && $dto->getCodePostal()) {
            $zipCities = $this->repoZipCity->findAllByPostalCode($dto->getCodePostal(), $dto->getCountry()->getId());
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        if (null !== $zipCity) {
            $city = $zipCity->getParent();
        }

        // City
        if (!$city && $dto->getVille()) {
            $cities = $this->repoCity->findAllByName($dto->getVille(), $dto->getCountry()->getId());
            if (1 === \count($cities)) {
                $city = $cities[0];
            }
        }

        $dto->setCity($city)->setZipCity($zipCity);
        if ($city) {
            $dto->setCountry($city->getCountry());
        } elseif (null !== $zipCity) {
            $dto->setCountry($zipCity->getCountry());
        }

        if (null !== $dto->getCity()) {
            $dto->getReject()->setReason(Reject::VALID);
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
                $this->entityManager->persist($exploration);
            }
            $this->entityManager->flush();
        }
        $this->entityManager->clear(ParserData::class);
        $this->firewall->flushParserDatas();
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function getAllowedEvents(array $events): array
    {
        return array_filter($events, fn (Event $event) => $this->firewall->isEventDtoValid($event));
    }

    /**
     * @param Event[] $events
     *
     * @return Event[]
     */
    private function getNotAllowedEvents(array $events): array
    {
        return array_filter($events, fn ($event) => !$this->firewall->isEventDtoValid($event));
    }

    /**
     * @param object[] $dtos
     *
     * @return (null|object)[]
     *
     * @psalm-return array<null|object>
     */
    private function mergeWithDatabase(
        array $dtos,
        DependencyCatalogue $previousCatalogue = null,
        array &$allCatalogues = [],
        array $paths = []
    ): array {
        if (0 === \count($dtos)) {
            return [];
        }

        $isRootTransaction = null === $previousCatalogue;

        $rootEntities = [];
        $chunks = ChunkUtils::getNestedChunksByClass($dtos, self::CHUNK_SIZE);

        // Per DTO class
        foreach ($chunks as $dtoClassName => $dtoChunks) {
            $currentPaths = $paths;
            $currentPaths[] = $dtoClassName;
            $this->logger->info(sprintf(
                '[%s] Traversing %d objects',
                implode(' > ', $currentPaths),
                \count($dtoChunks)
            ));

            $entityProvider = $this->entityProviderHandler->getEntityProvider($dtoClassName);
            $entityFactory = $this->entityFactoryHandler->getFactory($dtoClassName);

            // Per BATCH_SIZE
            foreach ($dtoChunks as $chunk) {
                // Resolve current dependencies before persisting root objects
                $requiredCatalogue = $this->computeRequiredCatalogue($chunk);
                $allCatalogues[] = $requiredCatalogue;
                $this->mergeWithDatabase($requiredCatalogue->objects(), $requiredCatalogue, $allCatalogues, $currentPaths);

                // Then perform a global SQL request to fetch entities by external ids
                $entityProvider->prefetchEntities($chunk);

                foreach ($chunk as $i => $dto) {
                    $isObjectReference = null !== $previousCatalogue
                        && $previousCatalogue->has($dto)
                        && $previousCatalogue->get($dto)->isReference();

                    // Fetch entity from previously prefetched ones
                    $entity = $entityProvider->getEntity($dto);
                    $isNewEntity = null === $entity;

                    // Resolve id
                    if (!$isNewEntity) {
                        $dtosToResolve = [$dto];
                        if ($previousCatalogue && $previousCatalogue->hasAliases($dto)) {
                            $dtosToResolve = [
                                ...$dtosToResolve,
                                ...$previousCatalogue->getAliases($dto),
                            ];
                        }

                        foreach ($dtosToResolve as $dtoToResolve) {
                            if (!$dtoToResolve instanceof DtoEntityIdentifierResolvableInterface) {
                                continue;
                            }

                            $dtoToResolve->setIdentifierFromEntity($entity);
                        }
                    }

                    // We don't create an empty entity into database if existing reference is not found
                    if ($isObjectReference) {
                        if ($isRootTransaction) {
                            $rootEntities[$i] = null;
                        }
                        continue;
                    }

                    // Either create a new entity from scratch
                    // Or merge dto with already existing one
                    $entity = $entityFactory->create($entity, $dto);

                    $this->entityManager->persist($entity);

                    if ($entity instanceof Event) {
                        if (
                            null === $entity->getPlace()
                            || null === $entity->getPlaceCountry()
                            || null === $entity->getPlace()->getCity()
                            || null === $entity->getPlace()->getCity()->getCountry()
                            || null === $entity->getPlace()->getCountry()
                        ) {
                            $foo = $this->entityProviderHandler->getEntityProvider(PlaceDto::class);
                            $bar = $dto->place;
                            $bar2 = $foo->getObjectKeys($dto->place);
                            $bar3 = $foo->getEntity($dto->place);
                            dump($bar3);
                            dd('NOK');
                        }

                        if (!$this->entityManager->contains($entity)) {
                            dump('nok');
                        }
                        if (!$this->entityManager->contains($entity->getPlaceCountry())) {
                            dump('nok');
                        }
                        if (!$this->entityManager->contains($entity->getPlace())) {
                            dump('nok');
                        }
                        if (!$this->entityManager->contains($entity->getPlace()->getCountry())) {
                            dump('nok');
                        }
                        if (!$this->entityManager->contains($entity->getPlace()->getCity())) {
                            dump('nok');
                        }
                    }

                    // Add all new entities to current samples in order to prevent duplicate creates
                    if ($isNewEntity) {
                        $entityProvider->addEntity(
                            $entity,
                            $dto instanceof DependencyObjectInterface
                            ? $dto->getUniqueKey()
                            : null
                        );
                    }

                    if ($isRootTransaction) {
                        $rootEntities[$i] = $entity;
                    }
                }

                // Resolve current dependencies after persisting parent objects
                $providedCatalogue = $this->computeProvidedCatalogue($chunk);
                $allCatalogues[] = $providedCatalogue;
                $this->mergeWithDatabase($providedCatalogue->objects(), $providedCatalogue, $allCatalogues, $currentPaths);

                if ($isRootTransaction) {
                    $this->logger->info(sprintf(
                        '[%s] FLUSH',
                        implode(' > ', $currentPaths),
                    ));
                    $this->entityManager->flush();

                    $entityProvider->clear();
                    foreach ($allCatalogues as $catalogue) {
                        $catalogue->clear();
                    }
                    $allCatalogues = [];
                    $this->entityManager->clear();
                    $this->logger->info(sprintf(
                        'Memory usage after flush: %s - Memory peak usage: %s',
                        MemoryUtils::getMemoryUsage(),
                        MemoryUtils::getPeakMemoryUsage(),
                    ));
                }
            }
        }

        return $rootEntities;

        // Par localisation
        foreach ($chunks as $chunk) {
            $this->echantillonHandler->prefetchPlaceEchantillons($this->unChunk($chunk));

            // Par n événements
            foreach ($chunk as $currentEvents) {
                $this->echantillonHandler->prefetchEventEchantillons($currentEvents);

                // Par événement
                foreach ($currentEvents as $i => $event) {
                    /** @var Event $event */
                    $echantillonPlaces = $this->echantillonHandler->getPlaceEchantillons($event);
                    $echantillonEvents = $this->echantillonHandler->getEventEchantillons($event);

                    $url = $event->getUrl();
                    $event = $this->handler->handle($echantillonEvents, $echantillonPlaces, $event);
                    if (!$this->firewall->isValid($event)) {
                        $this->parserHistoryHandler->addBlackList();
                    } else {
                        // Image URL has changed or never downloaded
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

        return $dtos;
    }

    private function computeRequiredCatalogue(array $dtos): DependencyCatalogueInterface
    {
        $catalogue = new DependencyCatalogue();
        foreach ($dtos as $dto) {
            if (!$dto instanceof DependencyRequirableInterface) {
                continue;
            }

            $catalogue->addCatalogue($dto->getRequiredCatalogue());
        }

        return $catalogue;
    }

    private function computeProvidedCatalogue(array $dtos): DependencyCatalogueInterface
    {
        $catalogue = new DependencyCatalogue();
        foreach ($dtos as $dto) {
            if (!$dto instanceof DependencyProvidableInterface) {
                continue;
            }

            $catalogue->addCatalogue($dto->getProvidedCatalogue());
        }

        return $catalogue;
    }

    /**
     * @param EventDto[] $events
     */
    private function getChunks(array $events): array
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
            $chunks[$i] = array_chunk($chunk, self::CHUNK_SIZE, true);
        }

        return $chunks;
    }

    private function commit(): void
    {
        try {
            $this->entityManager->flush();
        } catch (Exception $e) {
            Monitor::writeln(sprintf(
                '<error>%s</error>',
                $e->getMessage()
            ));
        }
    }

    private function clearEvents(): void
    {
        $this->entityManager->clear(Event::class);
        $this->echantillonHandler->clearEvents();
    }

    private function clearPlaces(): void
    {
        $this->entityManager->clear(Place::class);
        $this->echantillonHandler->clearPlaces();
    }

    /**
     * @param EventDto[] $dtos
     *
     * @return Event[]
     */
    public function handleManyCLI(array $dtos, bool $flush = true): array
    {
        $this->parserHistoryHandler->start();
        $dtos = $this->handleMany($dtos, $flush);
        $parserHistory = $this->parserHistoryHandler->stop();

        $this->entityManager->persist($parserHistory);
        $this->entityManager->flush();

        Monitor::writeln('');
        Monitor::displayStats();
        Monitor::displayTable([
            'NEWS' => $this->parserHistoryHandler->getNbInserts(),
            'UPDATES' => $this->parserHistoryHandler->getNbUpdates(),
            'BLACKLISTS' => $this->parserHistoryHandler->getNbBlackLists(),
            'EXPLORATIONS' => $this->parserHistoryHandler->getNbExplorations(),
        ]);

        $this->parserHistoryHandler->reset();

        return $dtos;
    }
}
