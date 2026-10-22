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
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\ArrayParameterType;
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

        $entityManager = $args->getObjectManager();
        $connection = $entityManager->getConnection();
        $ids = array_keys($events);

        $connection->executeStatement(
            // Without an alias on the updated table, which SQLite (the tests) refuses
            'UPDATE `event` SET comment_count = (SELECT COUNT(*) FROM comment c WHERE c.event_id = `event`.id AND c.parent_id IS NULL AND c.approved = 1) WHERE id IN (:ids)',
            ['ids' => $ids],
            ['ids' => ArrayParameterType::INTEGER],
        );

        // The page answering this request reads the new count (the header of a comment just posted)
        $counts = $connection->fetchAllKeyValue('SELECT id, comment_count FROM `event` WHERE id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
        foreach ($events as $id => $event) {
            $this->setCount($entityManager, $event, (int) ($counts[$id] ?? 0));
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
