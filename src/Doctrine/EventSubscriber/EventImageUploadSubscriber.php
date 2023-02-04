<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventSubscriber;

use App\Entity\Event;
use App\Handler\EventHandler;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Events;

class EventImageUploadSubscriber implements EventSubscriberInterface
{
    /** @var Event[] */
    private array $eventsToHandle = [];

    public function __construct(
        private EventHandler $eventHandler
    ) {
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::preFlush,
        ];
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
