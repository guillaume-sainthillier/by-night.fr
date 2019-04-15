<?php

namespace App\Listener;

use App\Entity\Exploration;
use App\Reject\Reject;
use App\Utils\Firewall;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EventListener
{
    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();
        $exploration = $entityManager->getRepository(Exploration::class)->findOneBy([
            'externalId' => $entity->getExternalId()
        ]);

        if (!$exploration) {
            $exploration = (new Exploration())
                ->setExternalId($entity->getExternalId());
        }

        $exploration
            ->setFirewallVersion(Firewall::VERSION)
            ->setLastUpdated($entity->getFbDateModification())
            ->setReason(Reject::EVENT_DELETED);

        $entityManager->persist($exploration);
    }
}
