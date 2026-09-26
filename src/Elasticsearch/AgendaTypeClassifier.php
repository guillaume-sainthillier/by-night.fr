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
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Index\MappingBuilder;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Stores on each event to come the agenda type pages that list it (Event::$agendaTypes), so the counts of the agenda's
 * type links are term lookups instead of every type's full-text search on each page.
 *
 * Run daily by app:events:classify-agenda-types: the types of an event imported or changed during the day are found
 * the next night. Only the events whose types changed are written, by DQL bulk updates (no lifecycle callback, no
 * updatedAt), then re-indexed through RefreshEventDocuments.
 */
final readonly class AgendaTypeClassifier
{
    private const int SCROLL_SIZE = 5_000;

    private const int BATCH_SIZE = 1_000;

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

        return $this->store($this->find($today), $today);
    }

    /**
     * Writes the types just found on the events that end from today on, and clears those of the events no type page
     * lists anymore.
     *
     * @param array<int, list<string>> $found the types of each event, by id (the events without any left out)
     *
     * @return int the number of events whose types changed
     */
    public function store(array $found, DateTimeImmutable $today): int
    {
        $stored = $this->eventRepository->findAgendaTypesEndingFrom($today);
        // An event whose sessions go on after its end date (inconsistent data the agenda still lists): read too, or
        // its types would be written again each night
        $stored += $this->eventRepository->findAgendaTypesOf(array_keys(array_diff_key($found, $stored)));

        $changes = [];
        foreach ($found as $id => $types) {
            if (($stored[$id] ?? []) !== $types) {
                $changes[implode(',', $types)][] = $id;
            }
        }

        foreach (array_keys(array_diff_key($stored, $found)) as $id) {
            $changes[''][] = $id;
        }

        foreach ($changes as $types => $ids) {
            foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
                $this
                    ->entityManager
                    ->createQueryBuilder()
                    ->update(Event::class, 'e')
                    ->set('e.agendaTypes', ':types')
                    ->where('e.id IN (:ids)')
                    ->setParameter('types', '' === $types ? [] : explode(',', $types), Types::SIMPLE_ARRAY)
                    ->setParameter('ids', $chunk)
                    ->getQuery()
                    ->execute();
            }
        }

        $written = array_merge(...array_values($changes));
        foreach (array_chunk($written, self::BATCH_SIZE) as $chunk) {
            $this->messageBus->dispatch(new RefreshEventDocuments(eventIds: $chunk));
        }

        return \count($written);
    }

    /**
     * @return array<int, list<string>> the types of each event that ends from today on, by id, in AgendaType order
     */
    private function find(DateTimeImmutable $today): array
    {
        /** @var EventElasticaRepository $repository */
        $repository = $this->repositoryManager->getRepository(Event::class);

        $found = [];
        foreach (AgendaType::cases() as $type) {
            $query = $repository->createAgendaTypeQuery($type, $today)->setSize(self::SCROLL_SIZE);
            foreach ($this->eventIndex->createSearch($query)->scroll() as $results) {
                foreach ($results->getResults() as $result) {
                    $found[(int) $result->getId()][] = $type->value;
                }
            }
        }

        return $found;
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
