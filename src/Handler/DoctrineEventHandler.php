<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Contracts\DependencyCatalogueInterface;
use App\Contracts\DependencyRequirableInterface;
use App\Contracts\DtoEntityIdentifierResolvableInterface;
use App\Contracts\EntityProviderInterface;
use App\Dependency\DependencyCatalogue;
use App\Dto\EventDto;
use App\Entity\ParserData;
use App\Exception\UncreatableEntityException;
use App\Import\EventFamilyResolver;
use App\Import\Firewall;
use App\Messenger\TransactionalMessageDispatcher;
use App\Reject\Reject;
use App\Utils\ChunkUtils;
use App\Utils\MemoryUtils;
use App\Utils\Monitor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class DoctrineEventHandler
{
    private const int CHUNK_SIZE = 50;

    private ParserHistoryHandler $parserHistoryHandler;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private EventHandler $handler,
        private Firewall $firewall,
        private EntityProviderHandler $entityProviderHandler,
        private EntityFactoryHandler $entityFactoryHandler,
        private EventImageDownloadScheduler $imageDownloadScheduler,
        private TransactionalMessageDispatcher $messageDispatcher,
        private EventFamilyResolver $familyResolver,
    ) {
        $this->parserHistoryHandler = new ParserHistoryHandler();
    }

    public function handleOne(EventDto $dto): void
    {
        $this->handleMany([$dto]);
    }

    /**
     * @param EventDto[] $dtos
     */
    public function handleMany(array $dtos): void
    {
        if ([] === $dtos) {
            return;
        }

        // Explorations (parser_data) and events must commit together. The content hash
        // stored on an exploration is a promise that this exact content is in the event
        // table, and both dedup gates (EventPublicationGuard before the queue, Firewall
        // after it) trust it. Committed before the merge, a failed batch was skipped for
        // good on retry: the hash matched, so nothing "had changed". Messages emitted in
        // between (image downloads, Elasticsearch documents) are held back until the
        // commit so their workers cannot look up rows that are not visible yet.
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        $this->messageDispatcher->begin();

        try {
            $this->doHandleMany($dtos);
            $connection->commit();
        } catch (Throwable $e) {
            $this->messageDispatcher->rollBack();
            $this->rollBack($connection);

            throw $e;
        }

        $this->messageDispatcher->commit();
    }

    private function rollBack(Connection $connection): void
    {
        try {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
        } catch (Throwable $rollbackException) {
            // Never mask the original failure; the connection is most likely gone anyway.
            $this->logger->error('Unable to roll back the failed import batch: {message}', [
                'message' => $rollbackException->getMessage(),
                'exception' => $rollbackException,
            ]);
        } finally {
            // Drop whatever the failed batch left in the unit of work (BatchResetListener
            // resets the per-batch services on clear) so a retry starts from a clean state.
            $this->entityManager->clear();
        }
    }

    /**
     * @param EventDto[] $dtos
     */
    private function doHandleMany(array $dtos): void
    {
        // Drop any leftover from a previously failed batch
        $this->imageDownloadScheduler->batchReset();

        // Retrieve all existing explorations for these events and their places
        $this->firewall->loadExplorations($dtos);

        // With this, we can already filter a good portion of events
        $this->filterEvents($dtos);

        // Then update the status of these explorations in the database
        $this->flushParserData();

        $allowedEvents = $this->getAllowedEvents($dtos);
        $dtos = null; // Call GC
        unset($dtos);

        // Clean event data
        $this->cleanEvents($allowedEvents);

        $this->mergeWithDatabase($allowedEvents);

        // Rows describing the same event under distinct external ids are grouped into a
        // family (a canonical plus redirecting duplicates lending it their dates) once
        // their content is in place. Same transaction: a family is never half-wired.
        $this->familyResolver->resolveForEvents($this->getEntityIds($allowedEvents));
        $this->entityManager->clear();
    }

    /**
     * Ids of the events the merge resolved or created for these DTOs.
     *
     * @param EventDto[] $dtos
     *
     * @return int[]
     */
    private function getEntityIds(array $dtos): array
    {
        $ids = [];
        foreach ($dtos as $dto) {
            if (null !== $dto->entityId) {
                $ids[$dto->entityId] = true;
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
                $exploration = $this->firewall->getEventExploration($dto);

                // An exploration has already taken place
                if (null !== $exploration) {
                    $this->firewall->filterEventExploration($exploration, $dto);
                    $reject = $exploration->getReject();

                    // This already led to the rejection of the event
                    if (false === $reject->isValid()) {
                        $this->parserHistoryHandler->addBlackList();
                        $dto->reject->setReason($reject->getReason());

                        continue;
                    }
                }
            }

            // The place is judged again each time, from what the source says of it now: its
            // checks are cheap, and a verdict kept from a previous run went on rejecting the
            // events of a venue the source had fixed since.
            $this->firewall->filterEvent($dto);
        }
    }

    private function flushParserData(): void
    {
        $explorations = $this->firewall->getExplorations();

        $chunks = array_chunk($explorations, 500);
        unset($explorations);

        foreach ($chunks as $chunk) {
            /** @var ParserData $exploration */
            foreach ($chunk as $exploration) {
                $exploration->setReason($exploration->getReject()->getReason());
                $this->parserHistoryHandler->addExploration();
                $this->entityManager->persist($exploration);
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $this->firewall->flushParserDatas();
    }

    /**
     * @param EventDto[] $dtos
     *
     * @return EventDto[]
     */
    private function getAllowedEvents(array $dtos): array
    {
        return array_filter($dtos, $this->firewall->isEventDtoValid(...));
    }

    /**
     * @param object[]                       $dtos
     * @param string[]                       $paths
     * @param DependencyCatalogueInterface[] $allCatalogues
     * @param EntityProviderInterface[]      $allEntityProviders
     */
    private function mergeWithDatabase(
        array $dtos,
        ?DependencyCatalogueInterface $previousCatalogue = null,
        array &$allCatalogues = [],
        array &$allEntityProviders = [],
        array $paths = [],
    ): void {
        if ([] === $dtos) {
            return;
        }

        $isRootTransaction = null === $previousCatalogue;

        $chunks = ChunkUtils::getNestedChunksByClass($dtos, self::CHUNK_SIZE);

        // Per DTO class
        foreach ($chunks as $dtoClassName => $dtoChunks) {
            $currentPaths = $paths;
            $currentPaths[] = $dtoClassName;
            $this->logger->info(\sprintf(
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

                // Pass 1: Fast prefetch by external IDs only
                $entityProvider->prefetchEntities($chunk, eager: false);

                // Pass 2: Eager prefetch for DTOs not resolved by external ID
                $unmatchedDtos = array_filter($chunk, static fn (object $dto): bool => null === $entityProvider->getEntity($dto));
                if ([] !== $unmatchedDtos) {
                    $entityProvider->prefetchEntities($unmatchedDtos, eager: true);
                }

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
                        if ($isRootTransaction) {
                            $this->parserHistoryHandler->addUpdate();
                        }

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
                    } elseif ($isRootTransaction) {
                        $this->parserHistoryHandler->addInsert();
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

                    $this->entityManager->persist($entity);

                    if ($isRootTransaction) {
                        $rootEntities[$i] = $entity;
                    }

                    // Add all new entities to current samples to prevent duplicate creates
                    if ($isNewEntity) {
                        $entityProvider->addEntity(
                            $entity,
                            $dto
                        );
                    }
                }

                if ($isRootTransaction) {
                    $this->logger->info(\sprintf(
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

                    // Queue image downloads now that events have ids (the message is
                    // released once the batch commits), but before the EntityManager
                    // clear() resets the scheduler.
                    $this->imageDownloadScheduler->dispatchPending();

                    // Clear entity providers
                    foreach ($allEntityProviders as $entityProviderToClear) {
                        $entityProviderToClear->clear();
                    }

                    $allEntityProviders = [];

                    // Clear catalogues
                    foreach ($allCatalogues as $catalogue) {
                        $catalogue->clear();
                    }

                    $allCatalogues = [];

                    // Finally, clear EM
                    $this->entityManager->clear();
                    $this->logger->info(\sprintf(
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

    /**
     * @param EventDto[] $dtos
     */
    public function handleManyCLI(array $dtos): void
    {
        $this->parserHistoryHandler->start();
        foreach ($dtos as $dto) {
            $this->parserHistoryHandler->addSource($dto->fromData);
        }

        try {
            $this->handleMany($dtos);
        } catch (Throwable $e) {
            // Counters of a failed batch must not leak into the next one
            $this->parserHistoryHandler->reset();

            throw $e;
        }

        $parserHistory = $this->parserHistoryHandler->stop();

        $this->entityManager->persist($parserHistory);
        $this->entityManager->flush();
        $this->entityManager->clear();

        Monitor::writeln('');
        Monitor::displayStats();
        Monitor::displayTable([
            'NEWS' => $this->parserHistoryHandler->getNbInserts(),
            'UPDATES' => $this->parserHistoryHandler->getNbUpdates(),
            'BLACKLISTS' => $this->parserHistoryHandler->getNbBlackLists(),
            'EXPLORATIONS' => $this->parserHistoryHandler->getNbExplorations(),
        ]);

        $this->parserHistoryHandler->reset();
    }
}
