<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Handler;

use App\Doctrine\EventListener\EventPageCachePurgeListener;
use App\Entity\Event;
use App\Repository\EventRepository;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;

/**
 * Downloads the images the sources give for their events, by batches: the events of an import batch once it is
 * committed (DownloadEventImagesHandler), or every event still waiting for its image (app:events:download-images).
 * Each batch is flushed, then the entity manager cleared.
 */
final readonly class EventImageDownloader
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EventRepository $eventRepository,
        private EventHandler $eventHandler,
        private EventPageCachePurgeListener $pageCachePurge,
    ) {
    }

    /**
     * @param int[] $eventIds
     * @param int[] $pageEventIds the events whose page the import left to purge with their image
     *                            (EventImageDownloadScheduler::defersPagePurge())
     */
    public function downloadEvents(array $eventIds, array $pageEventIds = []): void
    {
        if ([] === $eventIds) {
            return;
        }

        // With the download's flush, even when the image does not change (unreachable, or the same file): the page
        // must still show what else the import changed
        $this->pageCachePurge->purgeEventPages($pageEventIds);

        $this->download($this->eventRepository->findBy(['id' => $eventIds]));
    }

    /**
     * Every event waiting for its image (EventRepository::createWaitingForImageQueryBuilder()), by batches.
     *
     * @param DateTimeInterface|null $endingFrom only the events not over by that day
     *
     * @return Generator<int, int> the events of each batch, once it is downloaded
     */
    public function downloadWaiting(int $batchSize, ?DateTimeInterface $endingFrom = null): Generator
    {
        // Keyset pagination on the id: a downloaded image takes its event out of the filter, so
        // page numbers moved the offset past as many events as the page before had fixed, and
        // about half of the backlog was never attempted.
        $configurations = new OrderConfigurations(
            new OrderConfiguration('e.id', static fn (Event $event): ?int => $event->getId(), orderAscending: false),
        );
        /** @var CursorPagination<Event> $pagination */
        $pagination = new CursorPagination($this->eventRepository->createWaitingForImageQueryBuilder($endingFrom), $configurations, $batchSize, fetchJoinCollection: false);

        foreach ($pagination->getChunkResults() as $events) {
            $this->download($events);

            yield \count($events);
        }
    }

    /**
     * @param list<Event> $events
     */
    private function download(array $events): void
    {
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
