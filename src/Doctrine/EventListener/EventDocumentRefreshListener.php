<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Elasticsearch\Message\RefreshEventDocuments;
use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Entity\Place;
use App\Entity\Tag;
use App\Messenger\TransactionalMessageDispatcher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Service\ResetInterface;

/**
 * An event's search document copies its place, its category and themes, and its sessions,
 * but the index only follows changes to the event row itself: a place moved to another city
 * or renamed, a renamed tag, or sessions changed without the event row stayed as they were in
 * the documents until a full populate (a moved place kept its events in the old city's
 * agenda). The events concerned are re-indexed once the change is committed.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class EventDocumentRefreshListener implements ResetInterface
{
    /** What the event document reads from its place (Place's elasticsearch:event:details group) */
    private const array PLACE_FIELDS = ['name', 'street', 'cityName', 'cityPostalCode', 'city', 'country', 'latitude', 'longitude'];

    /** @var array<int, true> */
    private array $placeIds = [];

    /** @var array<int, true> */
    private array $tagIds = [];

    /** @var array<int, true> */
    private array $eventIds = [];

    public function __construct(private readonly TransactionalMessageDispatcher $messageDispatcher)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        // The index already follows these event rows
        $followedEvents = [];
        foreach ([...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            if ($entity instanceof Event && null !== $entity->getId()) {
                $followedEvents[$entity->getId()] = true;
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Place && null !== $entity->getId()
                && [] !== array_intersect(self::PLACE_FIELDS, array_keys($unitOfWork->getEntityChangeSet($entity)))) {
                $this->placeIds[$entity->getId()] = true;
            } elseif ($entity instanceof Tag && null !== $entity->getId()
                && \array_key_exists('name', $unitOfWork->getEntityChangeSet($entity))) {
                $this->tagIds[$entity->getId()] = true;
            }
        }

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            $eventId = $entity instanceof EventTimesheet ? $entity->getEvent()?->getId() : null;
            if (null !== $eventId && !isset($followedEvents[$eventId])) {
                $this->eventIds[$eventId] = true;
            }
        }
    }

    public function postFlush(): void
    {
        if ([] === $this->placeIds && [] === $this->tagIds && [] === $this->eventIds) {
            return;
        }

        $message = new RefreshEventDocuments(array_keys($this->placeIds), array_keys($this->tagIds), array_keys($this->eventIds));
        $this->reset();

        // Held until the commit when the flush is part of a transaction (an import batch)
        $this->messageDispatcher->dispatch($message);
    }

    public function reset(): void
    {
        $this->placeIds = [];
        $this->tagIds = [];
        $this->eventIds = [];
    }
}
