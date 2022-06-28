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
use App\Contracts\DependencyProvidableInterface;
use App\Contracts\DependencyRequirableInterface;
use App\Contracts\DtoEntityIdentifierResolvableInterface;
use App\Dependency\DependencyCatalogue;
use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Entity\Event;
use App\Entity\ParserData;
use App\Entity\Place;
use App\Exception\UncreatableEntityException;
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
    /**
     * @var int
     */
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

    public function handleOne(EventDto $dto, bool $flush = true): void
    {
        $this->handleMany([$dto], $flush);
    }

    /**
     * @param EventDto[] $dtos
     *
     * @return (null|object)[]
     *
     * @psalm-return array<null|object>
     */
    public function handleMany(array $dtos, bool $flush = true): void
    {
        if (0 === \count($dtos)) {
            return;
        }

        // On récupère toutes les explorations existantes pour ces événements
        $this->loadExternalIdsData($dtos);

        // Grace à ça, on peut déjà filtrer une bonne partie des événements
        $this->filterEvents($dtos);

        // On met ensuite à jour le statut de ces explorations en base
        $this->flushParserDatas();

        $allowedEvents = $this->getAllowedEvents($dtos);
        $dtos = null; // Call GC
        unset($dtos);

        // Clean event data
        $this->cleanEvents($allowedEvents);

        $this->mergeWithDatabase($allowedEvents);
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
    private function cleanEvents(array $dtos): void
    {
        foreach ($dtos as $dto) {
            $this->handler->cleanEvent($dto);
        }
    }

    /**
     * @param EventDto[] $dtos
     */
    private function filterEvents(array $dtos): void
    {
        foreach ($dtos as $dto) {
            $dto->reject = new Reject();

            if (null !== $dto->place) {
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
        if (null !== $dto->country && null !== $dto->country->name && null === $dto->country->code) {
            $country = $this->countryRepository->findOneByName($dto->country->name);
            $dto->country->code = $country?->getId();
        }

        // Pas de pays détecté -> next
        if (null === $dto->country || null === $dto->country->code) {
            if ($dto->country?->name) {
                $dto->reject->addReason(Reject::BAD_COUNTRY);
            } else {
                $dto->reject->addReason(Reject::NO_COUNTRY_PROVIDED);
            }

            return;
        }

        if (!$dto->city?->postalCode && !$dto->city?->name) {
            return;
        }

        // Location fournie -> Vérification dans la base des villes existantes
        $zipCity = null;
        $city = null;

        // Ville + CP
        if ($dto->city?->name && $dto->city?->postalCode) {
            $zipCity = $this->repoZipCity->findOneByPostalCodeAndCity($dto->city?->postalCode, $dto->city?->name, $dto->country->code);
        }

        // Ville
        if (!$zipCity && $dto->city?->name) {
            $zipCities = $this->repoZipCity->findAllByCity($dto->city?->name, $dto->country->code);
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        // CP
        if (!$zipCity && $dto->city?->postalCode) {
            $zipCities = $this->repoZipCity->findAllByPostalCode($dto->city?->postalCode, $dto->country?->code);
            if (1 === \count($zipCities)) {
                $zipCity = $zipCities[0];
            }
        }

        if (null !== $zipCity) {
            $city = $zipCity->getParent();
        }

        // City
        if (!$city && $dto->city?->name) {
            $cities = $this->repoCity->findAllByName($dto->city?->name, $dto->country?->code);
            if (1 === \count($cities)) {
                $city = $cities[0];
            }
        }

        $dto->city ??= new CityDto();
        if (null !== $city) {
            $dto->city->entityId = $city->getId();
            $dto->country ??= new CountryDto();
            $dto->country->code = $city->getCountry()->getId();
        } elseif (null !== $zipCity) {
            $dto->country ??= new CountryDto();
            $dto->country->code = $zipCity->getCountry()->getId();
        }

        if (null !== $dto->city) {
            $dto->reject->setReason(Reject::VALID);
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
     * @param EventDto[] $dtos
     *
     * @return EventDto[]
     */
    private function getAllowedEvents(array $dtos): array
    {
        return array_filter($dtos, fn (EventDto $dto) => $this->firewall->isEventDtoValid($dto));
    }

    /**
     * @param EventDto[] $dtos
     *
     * @return EventDto[]
     */
    private function getNotAllowedEvents(array $dtos): array
    {
        return array_filter($dtos, fn (EventDto $dto) => !$this->firewall->isEventDtoValid($dto));
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
        array &$allEntityProviders = [],
        array $paths = []
    ): void {
        if (0 === \count($dtos)) {
            return;
        }

        $isRootTransaction = null === $previousCatalogue;

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

            if (!\in_array($entityProvider, $allEntityProviders, true)) {
                $allEntityProviders[] = $entityProvider;
            }

            // Per BATCH_SIZE
            foreach ($dtoChunks as $chunk) {
                // Resolve current dependencies before persisting root objects
                $requiredCatalogue = $this->computeRequiredCatalogue($chunk);
                $allCatalogues[] = $requiredCatalogue;
                $this->mergeWithDatabase($requiredCatalogue->objects(), $requiredCatalogue, $allCatalogues, $allEntityProviders, $currentPaths);

                // Then perform a global SQL request to fetch entities by external ids
                $entityProvider->prefetchEntities($chunk);

                $rootEntities = [];
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
                            $dtosToResolve = array_merge(
                                $dtosToResolve,
                                $previousCatalogue->getAliases($dto),
                            );
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
                        continue;
                    }

                    // Either create a new entity from scratch
                    // Or merge dto with already existing one
                    try {
                        $entity = $entityFactory->create($entity, $dto);
                    } catch (UncreatableEntityException) {
                        continue;
                    }

                    if (!$isNewEntity && !$this->entityManager->contains($entity)) {
                        dump($entity);
                    }

                    $this->entityManager->persist($entity);

                    if ($isRootTransaction) {
                        $rootEntities[$i] = $entity;
                    }

                    /*
                    if ($entity instanceof Event) {
                        if (
                            null === $entity->getPlace()
                            || null === $entity->getPlaceCountry()
                            || null === $entity->getPlace()->getCountry()
                        ) {
                            $foo = $this->entityProviderHandler->getEntityProvider(PlaceDto::class);
                            $bar = $dto->place;
                            $bar2 = $foo->getObjectKeys($dto->place);
                            $bar3 = $foo->getEntity($dto->place);
                            dump($bar3);
                        }
                    }
                    */

                    // Add all new entities to current samples in order to prevent duplicate creates
                    if ($isNewEntity) {
                        $entityProvider->addEntity(
                            $entity,
                            $dto
                        );
                    }
                }

                // Resolve current dependencies after persisting parent objects
                $providedCatalogue = $this->computeProvidedCatalogue($chunk);
                $allCatalogues[] = $providedCatalogue;
                $this->mergeWithDatabase($providedCatalogue->objects(), $providedCatalogue, $allCatalogues, $allEntityProviders, $currentPaths);

                if ($isRootTransaction) {
                    $this->logger->info(sprintf(
                        '[%s] FLUSH',
                        implode(' > ', $currentPaths),
                    ));
                    $this->entityManager->flush();

                    // Update post insert ids
                    foreach ($chunk as $i => $dto) {
                        if (!$dto instanceof DtoEntityIdentifierResolvableInterface) {
                            continue;
                        }

                        if (empty($rootEntities[$i])) {
                            continue;
                        }

                        $dto->setIdentifierFromEntity($rootEntities[$i]);
                    }

                    foreach ($allEntityProviders as $entityProvider) {
                        $entityProvider->clear();
                    }

                    foreach ($allCatalogues as $catalogue) {
                        $catalogue->clear();
                    }

                    $allEntityProviders = [];
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
                $key = 'country.' . $event->getPlace()->country->code;
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
        } catch (Exception $exception) {
            Monitor::writeln(sprintf(
                '<error>%s</error>',
                $exception->getMessage()
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

        // $this->entityManager->persist($parserHistory);
        // $this->entityManager->flush();

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
