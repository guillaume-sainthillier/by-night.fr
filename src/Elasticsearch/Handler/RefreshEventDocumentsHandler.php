<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Elasticsearch\Handler;

use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Elasticsearch\Message\ReplaceManyDocuments;
use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\QueryBuilder;
use Elastica\Index;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Finds the indexed events concerned (a popular tag has thousands) and hands them to the
 * usual document updates, by chunks.
 */
#[AsMessageHandler]
final readonly class RefreshEventDocumentsHandler
{
    private const int CHUNK_SIZE = 500;

    public function __construct(
        private EventRepository $eventRepository,
        private MessageBusInterface $messageBus,
        #[Autowire(service: 'fos_elastica.index.event')]
        private Index $eventIndex,
    ) {
    }

    public function __invoke(RefreshEventDocuments $message): void
    {
        $ids = [];
        foreach ($this->findIndexedEventIds($message) as $id) {
            $ids[$id] = true;
        }

        foreach (array_chunk(array_keys($ids), self::CHUNK_SIZE) as $chunk) {
            $this->messageBus->dispatch(new ReplaceManyDocuments($this->eventIndex->getName(), Event::class, $chunk));
        }
    }

    /**
     * @return iterable<int>
     */
    private function findIndexedEventIds(RefreshEventDocuments $message): iterable
    {
        if ([] !== $message->placeIds) {
            yield from $this->ids($this->indexed()->andWhere('e.place IN (:places)')->setParameter('places', $message->placeIds));
        }

        if ([] !== $message->tagIds) {
            yield from $this->ids($this->indexed()->andWhere('e.category IN (:tags)')->setParameter('tags', $message->tagIds));
            yield from $this->ids($this->indexed()->join('e.themes', 'theme')->andWhere('theme.id IN (:tags)')->setParameter('tags', $message->tagIds));
        }

        if ([] !== $message->eventIds) {
            yield from $this->ids($this->indexed()->andWhere('e.id IN (:events)')->setParameter('events', $message->eventIds));
        }
    }

    /**
     * The events the index holds (see the index's query_builder_method): the others have
     * no document to refresh.
     */
    private function indexed(): QueryBuilder
    {
        return $this->eventRepository->createIsActiveQueryBuilder()->select('e.id');
    }

    /**
     * @return list<int>
     */
    private function ids(QueryBuilder $queryBuilder): array
    {
        return array_map(intval(...), $queryBuilder->getQuery()->getSingleColumnResult());
    }
}
