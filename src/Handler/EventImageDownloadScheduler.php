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
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Collects events whose image must be (re)downloaded during an import batch and
 * dispatches the work to a dedicated async transport once the events have been
 * persisted (and therefore have an id).
 *
 * This keeps the slow image download + S3 upload out of the import critical path.
 */
final class EventImageDownloadScheduler implements BatchResetInterface
{
    /** @var Event[] */
    private array $events = [];

    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function schedule(Event $event): void
    {
        if (!$event->getUrl()) {
            return;
        }

        $this->events[] = $event;
    }

    /**
     * Dispatch image downloads for the events collected so far. Must be called
     * after the events have been flushed (so their ids are available) and before
     * the EntityManager is cleared.
     */
    public function dispatchPending(): void
    {
        if ([] === $this->events) {
            return;
        }

        $ids = [];
        foreach ($this->events as $event) {
            $id = $event->getId();
            if (null !== $id) {
                $ids[$id] = true;
            }
        }

        $this->events = [];

        if ([] === $ids) {
            return;
        }

        $this->messageBus->dispatch(new DownloadEventImages(array_keys($ids)));
    }

    public function batchReset(): void
    {
        $this->events = [];
    }
}
