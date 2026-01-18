<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventSubscriber;

use App\Entity\Event;
use App\Handler\EventHandler;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::preFlush)]
final class EventImageUploadSubscriber
{
    /** @var Event[] */
    private array $eventsToHandle = [];

    public function __construct(
        private readonly EventHandler $eventHandler,
    ) {
    }

    public function preFlush(): void
    {
        $this->doDownloads();
    }

    public function handleEvent(Event $event): void
    {
        if (!$event->getUrl()) {
            return;
        }

        $this->eventsToHandle[] = $event;
    }

    public function doDownloads(): void
    {
        if ([] === $this->eventsToHandle) {
            return;
        }

        $this->eventHandler->handleDownloads($this->eventsToHandle);

        unset($this->eventsToHandle); // Calls GC
        $this->eventsToHandle = [];
    }
}
