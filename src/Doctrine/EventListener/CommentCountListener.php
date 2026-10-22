<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Entity\Comment;
use App\Entity\Event;
use App\Repository\CommentRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Recounts the comments of the events whose comments are written, moderated or deleted (with their member), once
 * flushed: the event page reads Event::getCommentCount() instead of counting them on every view, and skips their
 * queries altogether when there are none (most events).
 *
 * Counted as CommentRepository::findAllByEventQueryBuilder() lists them: approved, replies left out.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class CommentCountListener implements ResetInterface
{
    /** What decides whether and where a comment is counted */
    private const array COUNTED_FIELDS = ['event', 'parent', 'approved'];

    /** @var array<int, Event> */
    private array $events = [];

    public function __construct(private readonly CommentRepository $commentRepository)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            if ($entity instanceof Comment) {
                $this->add($entity->getEvent());
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Comment) {
                continue;
            }

            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            if ([] === array_intersect(self::COUNTED_FIELDS, array_keys($changeSet))) {
                continue;
            }

            // Moved to another event: the one it leaves loses it
            if (isset($changeSet['event'])) {
                $this->add($changeSet['event'][0]);
            }

            $this->add($entity->getEvent());
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $events = [];
        foreach ($this->events as $event) {
            if (null !== $event->getId()) {
                $events[$event->getId()] = $event;
            }
        }

        $this->reset();

        if ([] === $events) {
            return;
        }

        $counts = $this->commentRepository->countByEvents(array_keys($events));

        // One UPDATE per count: a flush usually touches a single event
        $byCount = [];
        foreach ($events as $id => $event) {
            $byCount[$counts[$id] ?? 0][] = $id;
        }

        $entityManager = $args->getObjectManager();
        foreach ($byCount as $count => $ids) {
            $entityManager
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
        foreach ($events as $id => $event) {
            $this->setCount($entityManager, $event, $counts[$id] ?? 0);
        }
    }

    public function reset(): void
    {
        $this->events = [];
    }

    private function add(mixed $event): void
    {
        if ($event instanceof Event) {
            $this->events[spl_object_id($event)] = $event;
        }
    }

    /**
     * Set as loaded, so the next flush does not write it again.
     */
    private function setCount(EntityManagerInterface $entityManager, Event $event, int $count): void
    {
        $event->setCommentCount($count);

        $unitOfWork = $entityManager->getUnitOfWork();
        if ($unitOfWork->isInIdentityMap($event)) {
            $unitOfWork->setOriginalEntityProperty(spl_object_id($event), 'commentCount', $count);
        }
    }
}
