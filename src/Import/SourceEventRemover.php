<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Repository\ParserDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Flags the events a source no longer lists (Event::markRemovedAtSource()): the page stays, open to everyone with
 * the status alert on top, while the draft flag takes the event out of every listing, the search index, the sitemap
 * and the upcoming counts. Listed again by its source, the event gets its own status back and comes out of the draft
 * (EventEntityFactory).
 */
final readonly class SourceEventRemover
{
    public function __construct(
        private EventRepository $eventRepository,
        private ParserDataRepository $parserDataRepository,
        private EventFamilyResolver $familyResolver,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<string> $externalIds  the records of the source that are gone
     * @param string|null  $sourcePrefix only the events whose source URL starts with it (see RemovedEventDto)
     *
     * @return int the events flagged
     */
    public function remove(string $externalOrigin, array $externalIds, ?string $sourcePrefix = null): int
    {
        $removed = [];
        foreach ($this->eventRepository->findByExternalIds($externalOrigin, $externalIds) as $event) {
            if (null !== $sourcePrefix && !str_starts_with((string) $event->getSource(), $sourcePrefix)) {
                continue;
            }

            if (!self::isRemovable($event)) {
                continue;
            }

            $event->markRemovedAtSource();
            $removed[(int) $event->getId()] = (string) $event->getExternalId();
        }

        if ([] === $removed) {
            return 0;
        }

        $this->entityManager->wrapInTransaction(function () use ($externalOrigin, $removed): void {
            $this->entityManager->flush();

            // The record listed again with the very content it had would be dropped by the dedup gate as unchanged,
            // and the event would stay removed
            $this->parserDataRepository->forgetContentHashes($externalOrigin, array_values($removed));

            // A removed row no longer lends its dates to its family, nor stays its canonical
            $this->familyResolver->resolveForEvents(array_keys($removed));
        });

        $this->logger->info('{count} event(s) of {origin} removed at the source', [
            'count' => \count($removed),
            'origin' => $externalOrigin,
        ]);

        return \count($removed);
    }

    /**
     * Whatever its dates (an event over is gone from the source as much as one to come) and its status (a cancelled
     * event withdrawn by its organiser is gone too), but never one a member published, which no source owns.
     */
    private static function isRemovable(Event $event): bool
    {
        return !$event->isRemovedAtSource() && null === $event->getUser();
    }
}
