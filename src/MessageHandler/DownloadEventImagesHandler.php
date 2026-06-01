<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Handler\EventHandler;
use App\Message\DownloadEventImages;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DownloadEventImagesHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private EventHandler $eventHandler,
    ) {
    }

    public function __invoke(DownloadEventImages $message): void
    {
        if ([] === $message->eventIds) {
            return;
        }

        $events = $this->eventRepository->findBy(['id' => $message->eventIds]);
        if ([] === $events) {
            return;
        }

        // Images are not part of the indexed document: flag the events so the
        // FOS Elastica listener skips re-indexing them (ConditionalUpdate).
        foreach ($events as $event) {
            $event->batchUpdate = true;
        }

        try {
            $this->eventHandler->handleDownloads($events);

            $this->entityManager->flush();
            $this->entityManager->clear();
        } finally {
            $this->eventHandler->reset();
        }
    }
}
