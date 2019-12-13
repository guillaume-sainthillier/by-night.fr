<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\EventListener;

use App\Entity\Event;
use App\Entity\Exploration;
use App\Reject\Reject;
use App\Utils\Firewall;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EventExplorationListener
{
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof Event || null === $entity->getExternalId()) {
            return;
        }
        $entityManager = $args->getEntityManager();
        $exploration = $entityManager->getRepository(Exploration::class)->findOneBy([
            'externalId' => $entity->getExternalId(),
        ]);

        if (!$exploration) {
            $exploration = (new Exploration())
                ->setExternalId($entity->getExternalId());
        }

        $exploration
            ->setFirewallVersion(Firewall::VERSION)
            ->setParserVersion($entity->getParserVersion())
            ->setLastUpdated($entity->getExternalUpdatedAt())
            ->setReason(Reject::EVENT_DELETED);

        $entityManager->persist($exploration);
    }
}
