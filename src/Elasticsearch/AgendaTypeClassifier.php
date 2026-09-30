<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch;

use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Entity\Event;
use App\Enum\AgendaType;
use App\Repository\EventRepository;
use App\SearchRepository\EventElasticaRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Elastica\Index;
use Elastica\Mapping;
use Elastica\PointInTime;
use Elastica\Result;
use Elastica\Search;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Index\MappingBuilder;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stores on each event to come the agenda type pages that list it (Event::$agendaTypes), so the type pages and the
 * counts of the agenda's type links are term lookups instead of every type's full-text search on each page.
 *
 * An event is classified once its document is written (classify(), see ClassifyEventsHandler): the type queries are
 * run on its document alone, named, and each hit tells the ones it matches. app:events:classify-agenda-types runs
 * the same search on every event with a session to come (refresh()), a page at a time through a point in time and
 * search_after, to fill the types of the events indexed before or after a change of the type terms.
 *
 * Only the events whose types changed are written, by DQL bulk updates (no lifecycle callback, no updatedAt), then
 * re-indexed through RefreshEventDocuments. The types of an event with no session to come are left as they are: no
 * search finds it, and it is counted nowhere.
 */
final readonly class AgendaTypeClassifier
{
    /** How long the point in time lives between two pages */
    private const string KEEP_ALIVE = '5m';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private RepositoryManagerInterface $repositoryManager,
        private MessageBusInterface $messageBus,
        #[Autowire(service: 'fos_elastica.index.event')]
        private Index $eventIndex,
        #[Autowire(service: 'fos_elastica.config_manager')]
        private ConfigManager $configManager,
        #[Autowire(service: 'fos_elastica.mapping_builder')]
        private MappingBuilder $mappingBuilder,
        /** The events of a page: read, compared and written together, what the memory holds */
        private int $pageSize = 1_000,
    ) {
    }

    /**
     * Classifies every event with a session to come.
     *
     * @return int the number of events whose types changed
     */
    public function refresh(DateTimeImmutable $today): int
    {
        $this->addMapping();

        $written = 0;
        foreach ($this->pages($today) as $page) {
            $written += \count($this->store($page));
        }

        return $written;
    }

    /**
     * Classifies these events, those with a session to come the index holds: the others are left as they are.
     *
     * @param list<int> $eventIds
     *
     * @return list<int> the events whose types changed
     */
    public function classify(array $eventIds, DateTimeImmutable $today): array
    {
        if ([] === $eventIds) {
            return [];
        }

        $query = $this->repository()->createAgendaTypesQuery($today, $eventIds)->setSize(\count($eventIds));

        return $this->store(self::typesOf($this->eventIndex->search($query)->getResults()));
    }

    /**
     * Writes the types found on these events, and re-indexes the ones whose types changed.
     *
     * @param array<int, list<string>> $found the types of each event, by id, in AgendaType order: none for an event no
     *                                        type page lists
     *
     * @return list<int> the events whose types changed
     */
    public function store(array $found): array
    {
        if ([] === $found) {
            return [];
        }

        $stored = $this->eventRepository->findAgendaTypesOf(array_keys($found));

        $changes = [];
        foreach ($found as $id => $types) {
            if (($stored[$id] ?? []) !== $types) {
                $changes[implode(',', $types)][] = $id;
            }
        }

        foreach ($changes as $types => $ids) {
            $this
                ->entityManager
                ->createQueryBuilder()
                ->update(Event::class, 'e')
                ->set('e.agendaTypes', ':types')
                ->where('e.id IN (:ids)')
                ->setParameter('types', '' === $types ? [] : explode(',', $types), Types::SIMPLE_ARRAY)
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();
        }

        $written = array_merge(...array_values($changes));
        if ([] !== $written) {
            $this->messageBus->dispatch(new RefreshEventDocuments(eventIds: $written));
        }

        return $written;
    }

    /**
     * The events with a session from today on, a page at a time, in index order (_shard_doc) through a point in time:
     * the documents re-indexed meanwhile neither move nor come twice.
     *
     * @return iterable<array<int, list<string>>> the types each page found, by event id
     */
    private function pages(DateTimeImmutable $today): iterable
    {
        $client = $this->eventIndex->getClient();
        $pointInTime = (string) $this->eventIndex->openPointInTime(self::KEEP_ALIVE)->getData()['id'];

        try {
            $after = null;
            do {
                $query = $this
                    ->repository()
                    ->createAgendaTypesQuery($today)
                    ->setSize($this->pageSize)
                    ->setPointInTime(new PointInTime($pointInTime, self::KEEP_ALIVE))
                    ->setSort(['_shard_doc' => 'asc']);
                if (null !== $after) {
                    // Elastica 9.0 has no setter for it yet
                    $query->setParam('search_after', $after);
                }

                // A search with a point in time names no index: the point in time holds it
                $results = new Search($client)->search($query);
                $pointInTime = $results->getPointInTimeId() ?? $pointInTime;
                $hits = $results->getResults();
                if ([] !== $hits) {
                    $after = $hits[array_key_last($hits)]->getSort();
                }

                yield self::typesOf($hits);
            } while ($this->pageSize === \count($hits));
        } finally {
            $client->closePointInTime($pointInTime);
        }
    }

    private function repository(): EventElasticaRepository
    {
        /** @var EventElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(Event::class);

        return $repository;
    }

    /**
     * @param Result[] $hits
     *
     * @return array<int, list<string>> the types of each hit, by event id, in AgendaType order
     */
    private static function typesOf(array $hits): array
    {
        $types = [];
        foreach ($hits as $hit) {
            // The names of the type queries it matched
            $matched = $hit->getHit()['matched_queries'] ?? [];
            $types[(int) $hit->getId()] = array_values(array_map(
                static fn (AgendaType $type): string => $type->value,
                array_filter(AgendaType::cases(), static fn (AgendaType $type): bool => \in_array($type->value, $matched, true)),
            ));
        }

        return $types;
    }

    /**
     * An index created before the field has no mapping for it, and the first document written would map it as text:
     * the configured mapping of the field is added first (again on each run, which changes nothing once there).
     */
    private function addMapping(): void
    {
        $mapping = $this->mappingBuilder->buildMapping(null, $this->configManager->getIndexConfiguration('event'));

        $this->eventIndex->setMapping(new Mapping(['agendaTypes' => $mapping['properties']['agendaTypes']]));
    }
}
