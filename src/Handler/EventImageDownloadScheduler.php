<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Contracts\BatchResetInterface;
use App\Entity\Event;
use App\Message\DownloadEventImages;
use App\Messenger\TransactionalMessageDispatcher;

/**
 * Collects events whose image must be (re)downloaded during an import batch and
 * dispatches the work to a dedicated async transport once the events have been
 * persisted (and therefore have an id).
 *
 * This keeps the slow image download + S3 upload out of the import critical path.
 * The message goes through the TransactionalMessageDispatcher, so it only leaves
 * once the batch transaction is committed and the event rows are visible.
 */
final class EventImageDownloadScheduler implements BatchResetInterface
{
    /** @var Event[] */
    private array $events = [];

    /** @var array<int, true> the scheduled events already saved, by object id: their page purge waits for the image */
    private array $deferredPagePurges = [];

    public function __construct(
        private readonly TransactionalMessageDispatcher $messageDispatcher,
    ) {
    }

    public function schedule(Event $event): void
    {
        // An image taken down on request is not downloaded again (EventImageRemover)
        if (!$event->getUrl() || null !== $event->getImageRemovedAt()) {
            return;
        }

        $this->events[] = $event;
        if (null !== $event->getId()) {
            $this->deferredPagePurges[spl_object_id($event)] = true;
        }
    }

    /**
     * Whether the page of this event is purged once its new image is stored rather than now: the import would
     * otherwise purge it twice, minutes apart, which Cloudflare's tag quota pays for (EventPageCachePurgeListener).
     * A new event has no page to purge.
     */
    public function defersPagePurge(Event $event): bool
    {
        return isset($this->deferredPagePurges[spl_object_id($event)]);
    }

    /**
     * Dispatch image downloads for the events collected so far. Must be called
     * after the events have been flushed (so their ids are available) and before
     * the EntityManager is cleared. The message itself is held back until the
     * surrounding batch transaction commits.
     */
    public function dispatchPending(): void
    {
        if ([] === $this->events) {
            return;
        }

        $ids = [];
        $pageIds = [];
        foreach ($this->events as $event) {
            $id = $event->getId();
            if (null !== $id) {
                $ids[$id] = true;
                if ($this->defersPagePurge($event)) {
                    $pageIds[$id] = true;
                }
            }
        }

        $this->batchReset();

        if ([] === $ids) {
            return;
        }

        $this->messageDispatcher->dispatch(new DownloadEventImages(array_keys($ids), array_keys($pageIds)));
    }

    public function batchReset(): void
    {
        $this->events = [];
        $this->deferredPagePurges = [];
    }
}
