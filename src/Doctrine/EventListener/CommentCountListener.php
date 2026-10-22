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
use App\Manager\CommentCountManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Recounts the comments of the events whose comments are written, moderated or deleted (with their member), once
 * flushed (CommentCountManager).
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class CommentCountListener implements ResetInterface
{
    /** What decides whether and where a comment is counted */
    private const array COUNTED_FIELDS = ['event', 'parent', 'approved'];

    /** @var array<int, Event> */
    private array $events = [];

    public function __construct(private readonly CommentCountManager $commentCountManager)
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

    public function postFlush(): void
    {
        $events = $this->events;
        $this->reset();

        if ([] !== $events) {
            $this->commentCountManager->recount($events);
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
}
