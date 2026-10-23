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
use App\Message\CheckOutingChanges;
use App\Messenger\TransactionalMessageDispatcher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Asks for the members going to the events whose dates, venue or status are changed to hear of it
 * (OutingChangeNotifier), whoever changed them: an import, the organizer, the back office.
 *
 * Only the event ids leave here, and a while later: an import writes an event of a family with its own dates, then
 * EventFamilyResolver widens them to its family's in the next flush, so a flush alone does not tell what changed. The
 * notifier compares the event as it then is to what each member knows of it. A duplicate is left out: its page leads to
 * its canonical's.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class OutingChangeListener implements ResetInterface
{
    /** What an alert tells of (OutingState) */
    private const array WATCHED_FIELDS = ['startDate', 'endDate', 'startTime', 'place', 'status'];

    /** Events per message: one query each */
    private const int BATCH_SIZE = 500;

    /** How long the change has to settle: an import's batch and its families' flush are long done */
    private const int DELAY_MS = 15 * 60 * 1000;

    /** @var array<int, true> */
    private array $eventIds = [];

    public function __construct(private readonly TransactionalMessageDispatcher $messageDispatcher)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Event || null === $entity->getId() || $entity->isDuplicate()) {
                continue;
            }

            if ([] !== array_intersect(self::WATCHED_FIELDS, array_keys($unitOfWork->getEntityChangeSet($entity)))) {
                $this->eventIds[$entity->getId()] = true;
            }
        }
    }

    public function postFlush(): void
    {
        $eventIds = array_keys($this->eventIds);
        $this->reset();

        foreach (array_chunk($eventIds, self::BATCH_SIZE) as $chunk) {
            // Held until the commit when the flush is part of a transaction (DoctrineEventHandler::handleOne())
            $this->messageDispatcher->dispatch(Envelope::wrap(new CheckOutingChanges($chunk), [new DelayStamp(self::DELAY_MS)]));
        }
    }

    public function reset(): void
    {
        $this->eventIds = [];
    }
}
