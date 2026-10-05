<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\MessageHandler;

use App\Entity\Event;
use App\Enum\EventStatus;
use App\Import\EventFamilyResolver;
use App\Message\RemoveSourceEvents;
use App\Repository\EventRepository;
use App\Repository\ParserDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Flags the events a source no longer lists as EventStatus::Removed. The page stays, open to everyone with the
 * status alert on top, while the draft flag takes the event out of every listing, the search index, the sitemap
 * and the upcoming counts. Listed again by its source, the event gets its own status back and comes out of the
 * draft (EventEntityFactory).
 */
#[AsMessageHandler]
final readonly class RemoveSourceEventsHandler
{
    public function __construct(
        private EventRepository $eventRepository,
        private ParserDataRepository $parserDataRepository,
        private EventFamilyResolver $familyResolver,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RemoveSourceEvents $message): void
    {
        $removed = [];
        foreach ($this->eventRepository->findByExternalIds($message->externalOrigin, $message->externalIds) as $event) {
            if (null !== $message->sourcePrefix && !str_starts_with((string) $event->getSource(), $message->sourcePrefix)) {
                continue;
            }

            if (!$this->shouldFlag($event)) {
                continue;
            }

            $event
                ->setStatus(EventStatus::Removed)
                ->setStatusMessage(null)
                ->setDraft(true);
            $removed[(int) $event->getId()] = (string) $event->getExternalId();
        }

        if ([] === $removed) {
            return;
        }

        $this->entityManager->wrapInTransaction(function () use ($message, $removed): void {
            $this->entityManager->flush();

            // The record listed again with the very content it had would be dropped by the dedup gate as unchanged,
            // and the event would stay removed
            $this->parserDataRepository->forgetContentHashes($message->externalOrigin, array_values($removed));

            // A removed row no longer lends its dates to its family, nor stays its canonical
            $this->familyResolver->resolveForEvents(array_keys($removed));
        });

        $this->logger->info('{count} event(s) of {origin} removed at the source', [
            'count' => \count($removed),
            'origin' => $message->externalOrigin,
        ]);
    }

    /**
     * Whether an event its source no longer lists gets flagged as removed: whatever its dates (an event over is
     * gone from the source as much as one to come) and its status (a cancelled event withdrawn by its organiser
     * is gone too), but never one a member published, which no source owns.
     */
    private function shouldFlag(Event $event): bool
    {
        return !$event->isRemovedAtSource() && null === $event->getUser();
    }
}
