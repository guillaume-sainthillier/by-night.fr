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
use App\Handler\EventImageDownloadScheduler;
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
 * every event the source still lists). An imported event whose new image is still to download is purged once
 * that image is stored, not twice (EventImageDownloadScheduler::defersPagePurge(), EventImageDownloader). A
 * duplicate's change purges its canonical's page too, which lists its ticketing offer (EventTicketOffers).
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

    public function __construct(
        private readonly TransactionalMessageDispatcher $messageDispatcher,
        private readonly EventImageDownloadScheduler $imageDownloadScheduler,
    ) {
    }

    /**
     * Purges the pages of these events with the next flush, whatever it changes.
     *
     * @param int[] $eventIds
     */
    public function purgeEventPages(array $eventIds): void
    {
        foreach ($eventIds as $eventId) {
            $this->tags[EventPageCache::eventTag($eventId)] = true;
        }
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Event) {
                $this->purgeCanonicals($entity, []);
            }
        }

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Event && null !== $entity->getId()) {
                $this->tags[EventPageCache::eventTag($entity->getId())] = true;
                $this->purgeCanonicals($entity, []);
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Event && null !== $entity->getId()
                && [] !== array_diff(array_keys($changeSet = $unitOfWork->getEntityChangeSet($entity)), self::UNSHOWN_EVENT_FIELDS)) {
                // The image still to download defers the event's own page, not its canonical's: it shows its price,
                // status and link, and a borrowed picture purges it through its lender's tag (EventPageCache)
                if (!$this->imageDownloadScheduler->defersPagePurge($entity)) {
                    $this->tags[EventPageCache::eventTag($entity->getId())] = true;
                }

                $this->purgeCanonicals($entity, $changeSet);
            } elseif ($entity instanceof Place && null !== $entity->getId()
                && [] !== array_intersect(self::PLACE_FIELDS, array_keys($unitOfWork->getEntityChangeSet($entity)))) {
                $this->tags[EventPageCache::placeTag($entity->getId())] = true;
            }
        }

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            $event = $entity instanceof EventTimesheet || $entity instanceof Comment || $entity instanceof UserEvent
                ? $entity->getEvent()
                : null;
            if (null !== $event && null !== $event->getId() && !$this->imageDownloadScheduler->defersPagePurge($event)) {
                $this->tags[EventPageCache::eventTag($event->getId())] = true;
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

    /**
     * The page of the canonical a duplicate redirects to, and of the one it left: both list (or listed) its offer.
     *
     * @param array<string, array{mixed, mixed}> $changeSet
     */
    private function purgeCanonicals(Event $event, array $changeSet): void
    {
        $canonicals = [$event->getDuplicateOf(), ...(isset($changeSet['duplicateOf']) ? [$changeSet['duplicateOf'][0]] : [])];
        foreach ($canonicals as $canonical) {
            if ($canonical instanceof Event && null !== $canonical->getId() && !$this->imageDownloadScheduler->defersPagePurge($canonical)) {
                $this->tags[EventPageCache::eventTag($canonical->getId())] = true;
            }
        }
    }
}
