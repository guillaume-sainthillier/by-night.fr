<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Publisher;
use App\Enum\EventBulkAction;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Publishes, hides or deletes several of a member's events at once: the ones of the given ids that are theirs, the
 * others left alone (a forged id is no way into another member's events). One flush: the page cache and search
 * listeners see the whole batch.
 */
final readonly class MemberEventBulkEditor
{
    /** The events a single action takes at most: a list page, an import */
    public const int MAX_EVENTS = 1000;

    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<int> $ids
     *
     * @return int the events it acted on
     */
    public function apply(Publisher $publisher, array $ids, EventBulkAction $action): int
    {
        $ids = \array_slice(array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0))), 0, self::MAX_EVENTS);
        if ([] === $ids) {
            return 0;
        }

        $events = $this->eventRepository->findByPublisherAndIds($publisher, $ids);
        foreach ($events as $event) {
            match ($action) {
                EventBulkAction::Publish => $event->setDraft(false),
                EventBulkAction::Draft => $event->setDraft(true),
                EventBulkAction::Delete => $this->entityManager->remove($event),
            };
        }

        $this->entityManager->flush();

        return \count($events);
    }
}
