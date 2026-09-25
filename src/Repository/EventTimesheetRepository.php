<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Repository;

use App\Contracts\MultipleEagerLoaderInterface;
use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Manager\PreloadManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventTimesheet>
 *
 * @implements MultipleEagerLoaderInterface<EventTimesheet>
 */
final class EventTimesheetRepository extends ServiceEntityRepository implements MultipleEagerLoaderInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PreloadManager $preloadManager,
    ) {
        parent::__construct($registry, EventTimesheet::class);
    }

    public function loadAllEager(array $entities, array $context = []): void
    {
        if ('admin:index' !== ($context['view'] ?? null)) {
            return;
        }

        // The event a date belongs to and the family duplicate it is inherited from, in one query
        $this->preloadManager->preloadEntities(Event::class, [
            ...array_map(static fn (EventTimesheet $entity) => $entity->getEvent()?->getId(), $entities),
            ...array_map(static fn (EventTimesheet $entity) => $entity->getSourceEvent()?->getId(), $entities),
        ]);
    }
}
