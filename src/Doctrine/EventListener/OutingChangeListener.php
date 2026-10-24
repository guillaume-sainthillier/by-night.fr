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
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Clock\ClockInterface;

/**
 * Flags the events whose dates, venue or status are changed (Event::$flaggedForAlertAt), whoever changed them: an
 * import, the organizer, the back office. The members going to them hear of it from the next run of
 * app:outing-alerts:send (OutingChangeNotifier), a few times a day.
 *
 * Only a flag is set here, written by the same UPDATE as the change: an import writes an event of a family with its own
 * dates, then EventFamilyResolver widens them to its family's in the next flush, so a flush alone does not tell what
 * changed. The notifier compares the event as it then is to what each member knows of it. Each change moves the flag to
 * its own time, so that a change made while a run is telling the previous one is left for the next run. A duplicate is
 * left out: its page leads to its canonical's.
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class OutingChangeListener
{
    /**
     * What an alert tells of (OutingState), and the draft: what changed while its author hid the event is told once it
     * is published again
     */
    private const array WATCHED_FIELDS = ['startDate', 'endDate', 'startTime', 'place', 'status', 'draft'];

    public function __construct(private ClockInterface $clock)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        $now = null;

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Event || null === $entity->getId() || $entity->isDuplicate()) {
                continue;
            }

            if ([] === array_intersect(self::WATCHED_FIELDS, array_keys($unitOfWork->getEntityChangeSet($entity)))) {
                continue;
            }

            $entity->setFlaggedForAlertAt($now ??= $this->clock->now());
            $unitOfWork->recomputeSingleEntityChangeSet($entityManager->getClassMetadata(Event::class), $entity);
        }
    }
}
