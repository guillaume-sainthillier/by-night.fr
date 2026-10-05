<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Cdn\CloudflareCdnPurger;
use App\Cdn\EventPageCache;
use App\Entity\Comment;
use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Entity\Place;
use App\Entity\UserEvent;
use App\Message\PurgeCdnCacheTags;
use App\Messenger\TransactionalMessageDispatcher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Purges from Cloudflare the event pages a flush changed, which it otherwise keeps up to a week (EventPageCache):
 * the event row itself (an edit, a cancellation, a deletion), its sessions, comments and participants, and the
 * address of its place. Bulk DQL updates (the nightly agenda classification) bypass the unit of work, so they
 * purge nothing, and neither does an event row whose change the page does not show (a parser version bump stamps
 * every event the source still lists).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class EventPageCachePurgeListener implements ResetInterface
{
    /** What an event page shows of its place */
    private const array PLACE_FIELDS = ['name', 'street', 'cityName', 'cityPostalCode', 'city', 'country', 'latitude', 'longitude'];

    /** What an event row holds for the import and the back office only */
    private const array UNSHOWN_EVENT_FIELDS = ['updatedAt', 'parserVersion', 'externalUpdatedAt', 'identityHash'];

    /** @var array<string, true> */
    private array $tags = [];

    public function __construct(private readonly TransactionalMessageDispatcher $messageDispatcher)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Event && null !== $entity->getId()) {
                $this->tags[EventPageCache::eventTag($entity->getId())] = true;
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Event && null !== $entity->getId()
                && [] !== array_diff(array_keys($unitOfWork->getEntityChangeSet($entity)), self::UNSHOWN_EVENT_FIELDS)) {
                $this->tags[EventPageCache::eventTag($entity->getId())] = true;
            } elseif ($entity instanceof Place && null !== $entity->getId()
                && [] !== array_intersect(self::PLACE_FIELDS, array_keys($unitOfWork->getEntityChangeSet($entity)))) {
                $this->tags[EventPageCache::placeTag($entity->getId())] = true;
            }
        }

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            $eventId = $entity instanceof EventTimesheet || $entity instanceof Comment || $entity instanceof UserEvent
                ? $entity->getEvent()?->getId()
                : null;
            if (null !== $eventId) {
                $this->tags[EventPageCache::eventTag($eventId)] = true;
            }
        }
    }

    public function postFlush(): void
    {
        $tags = array_map(strval(...), array_keys($this->tags));
        $this->reset();

        // Held until the commit when the flush is part of a transaction (an import batch). One message per request:
        // the "cdn" worker still packs those of consecutive flushes together (PurgeCdnCacheTagsHandler)
        foreach (array_chunk($tags, CloudflareCdnPurger::MAX_FILES_PER_REQUEST) as $chunk) {
            $this->messageDispatcher->dispatch(new PurgeCdnCacheTags($chunk));
        }
    }

    public function reset(): void
    {
        $this->tags = [];
    }
}
