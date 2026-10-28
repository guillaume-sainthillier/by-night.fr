<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Entity\Event;
use App\Picture\EventCard;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Deletes the social images of the events a flush deletes (see EventCard), once it has gone through: a flush that fails
 * keeps them. The ids are read in onFlush, the entities losing theirs once deleted; a DQL bulk delete deletes none.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class EventCardRemovalListener implements ResetInterface
{
    /** @var list<int> */
    private array $eventIds = [];

    public function __construct(private readonly EventCard $eventCard)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Event && null !== $entity->getId()) {
                $this->eventIds[] = $entity->getId();
            }
        }
    }

    public function postFlush(): void
    {
        $eventIds = $this->eventIds;
        $this->eventIds = [];
        foreach ($eventIds as $eventId) {
            $this->eventCard->remove($eventId);
        }
    }

    public function reset(): void
    {
        $this->eventIds = [];
    }
}
