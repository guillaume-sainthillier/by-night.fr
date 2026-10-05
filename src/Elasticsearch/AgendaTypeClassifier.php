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
use Elastica\Result;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Index\MappingBuilder;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stores on each event to come the agenda type pages that list it (Event::$agendaTypes), so the type pages and the
 * counts of the agenda's type links are term lookups instead of every type's full-text search on each page.
 *
 * Run daily by app:events:classify-agenda-types: the types of an event imported or changed during the day are found
 * the next night. One search names the types of every event with a session to come (each hit tells the type queries
 * it matches), read through a scroll: the fuzzy terms of the type queries expand over the index once, not once a
 * page, and each page is compared with the types stored on its events and written before the next one is read, so
 * memory holds one page whatever the size of the index. Only the events whose types changed are written, by DQL bulk
 * updates (no lifecycle callback, no updatedAt), then re-indexed through RefreshEventDocuments.
 *
 * The types of an event with no session to come are left as they are: the search does not find it, and no page or
 * count reads them.
 */
final readonly class AgendaTypeClassifier
{
    /** The events of a page: read, compared and written together */
    private const int PAGE_SIZE = 1_000;

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
    ) {
    }

    /**
     * @return int the number of events whose types changed
     */
    public function refresh(DateTimeImmutable $today): int
    {
        $this->addMapping();

        /** @var EventElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(Event::class);
        $query = $repository->createAgendaTypesQuery($today)->setSize(self::PAGE_SIZE);

        $written = 0;
        foreach ($this->eventIndex->createSearch($query)->scroll() as $results) {
            $written += $this->store(self::typesOf($results->getResults()));
        }

        return $written;
    }

    /**
     * Writes the types found on a page of events, and re-indexes the ones whose types changed.
     *
     * @param array<int, list<string>> $found the types of each event of the page, by id, in AgendaType order: none for
     *                                        an event no type page lists
     *
     * @return int the number of events whose types changed
     */
    public function store(array $found): int
    {
        if ([] === $found) {
            return 0;
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

        return \count($written);
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
     * the configured mapping of the field is added first (again each night, which changes nothing once there).
     */
    private function addMapping(): void
    {
        $mapping = $this->mappingBuilder->buildMapping(null, $this->configManager->getIndexConfiguration('event'));

        $this->eventIndex->setMapping(new Mapping(['agendaTypes' => $mapping['properties']['agendaTypes']]));
    }
}
