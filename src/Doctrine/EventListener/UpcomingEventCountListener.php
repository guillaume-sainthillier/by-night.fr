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
use App\Entity\Place;
use App\Message\RecountUpcomingEvents;
use App\Messenger\TransactionalMessageDispatcher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Recounts the events to come of the venues (and their cities and countries) whose events are created, changed or
 * deleted on the site, by a member in their personal space or by an admin in the back office, once the change is
 * committed: the nightly app:events:count-upcoming would only show them the next day.
 *
 * Imports are left to the nightly count: they run in workers and commands, outside any request, and change thousands
 * of events a day on venues the parser worker keeps writing.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class UpcomingEventCountListener implements ResetInterface
{
    /** What decides whether and where an event is counted (EventRepository::whereUpcoming() and its category) */
    private const array COUNTED_FIELDS = ['place', 'draft', 'duplicateOf', 'endDate', 'category'];

    /**
     * Read in postFlush: a venue created in the same flush has no id yet in onFlush.
     *
     * @var array<int, Place>
     */
    private array $places = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TransactionalMessageDispatcher $messageDispatcher,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        if (null === $this->requestStack->getMainRequest()) {
            return;
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityDeletions()] as $entity) {
            if ($entity instanceof Event) {
                $this->add($entity->getPlace());
            }
        }

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Event) {
                continue;
            }

            $changeSet = $unitOfWork->getEntityChangeSet($entity);
            if ([] === array_intersect(self::COUNTED_FIELDS, array_keys($changeSet))) {
                continue;
            }

            // Moved to another venue: the one it leaves loses it
            if (isset($changeSet['place'])) {
                $this->add($changeSet['place'][0]);
            }

            $this->add($entity->getPlace());
        }
    }

    public function postFlush(): void
    {
        if ([] === $this->places) {
            return;
        }

        $placeIds = [];
        foreach ($this->places as $place) {
            if (null !== $place->getId()) {
                $placeIds[$place->getId()] = true;
            }
        }

        $this->reset();

        if ([] !== $placeIds) {
            // Held until the commit when the flush is part of a transaction (DoctrineEventHandler::handleOne())
            $this->messageDispatcher->dispatch(new RecountUpcomingEvents(array_keys($placeIds)));
        }
    }

    public function reset(): void
    {
        $this->places = [];
    }

    private function add(mixed $place): void
    {
        if ($place instanceof Place) {
            $this->places[spl_object_id($place)] = $place;
        }
    }
}
