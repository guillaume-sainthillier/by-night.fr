<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Event;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The comment counter of the events (Event::$commentCount): the event page reads it instead of counting the comments
 * on every view, and skips their queries altogether when there are none (most events). Recounted from the comments as
 * flushed, never moved by one: a missed or doubled update cannot leave it wrong for good.
 */
final readonly class CommentCountManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommentRepository $commentRepository,
    ) {
    }

    /**
     * Stores the counters of the events from their comments as flushed, and sets them on the entities as loaded, so the
     * next flush does not write them again. Runs its own UPDATEs: callable from a postFlush listener.
     *
     * @param iterable<Event> $events
     */
    public function recount(iterable $events): void
    {
        $byId = [];
        foreach ($events as $event) {
            if (null !== $event->getId()) {
                $byId[$event->getId()] = $event;
            }
        }

        if ([] === $byId) {
            return;
        }

        $counts = $this->commentRepository->countByEvents(array_keys($byId));

        // One UPDATE per count: a flush usually touches a single event
        $byCount = [];
        foreach ($byId as $id => $event) {
            $byCount[$counts[$id] ?? 0][] = $id;
        }

        foreach ($byCount as $count => $ids) {
            $this->entityManager
                ->createQueryBuilder()
                ->update(Event::class, 'e')
                ->set('e.commentCount', ':count')
                ->where('e.id IN (:ids)')
                ->setParameter('count', $count)
                ->setParameter('ids', $ids)
                ->getQuery()
                ->execute();
        }

        // The page answering this request reads the new count (the header of a comment just posted)
        $unitOfWork = $this->entityManager->getUnitOfWork();
        foreach ($byId as $id => $event) {
            $count = $counts[$id] ?? 0;
            $event->setCommentCount($count);
            if ($unitOfWork->isInIdentityMap($event)) {
                $unitOfWork->setOriginalEntityProperty(spl_object_id($event), 'commentCount', $count);
            }
        }
    }
}
