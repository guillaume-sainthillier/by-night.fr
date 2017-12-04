<?php

namespace App\Listener;

use App\Entity\City;
use Doctrine\ORM\Event\LifecycleEventArgs;
use App\Entity\Agenda;
use App\Invalidator\EventInvalidator;
use App\Entity\Exploration;
use App\Reject\Reject;
use App\Utils\Firewall;
use App\Entity\User;

class EventListener
{
    /**
     * @var EventInvalidator
     */
    private $eventInvalidator;

    public function __construct(EventInvalidator $eventInvalidator)
    {
        $this->eventInvalidator = $eventInvalidator;
    }

    public function postFlush()
    {
        $this->eventInvalidator->invalidateObjects();
    }

    public function postInsert(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof City) {
            $this->eventInvalidator->addCity($entity);

            return;
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof User) {
            $this->eventInvalidator->addUser($entity);

            return;
        }

        if ($entity instanceof City) {
            $this->eventInvalidator->addCity($entity);

            return;
        }

        if (!$entity instanceof Agenda) {
            return;
        }

        $this->eventInvalidator->addEvent($entity);
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        if ($entity instanceof User) {
            $this->eventInvalidator->addUser($entity);

            return;
        }

        if ($entity instanceof City) {
            $this->eventInvalidator->addCity($entity);

            return;
        }

        if (!$entity instanceof Agenda) {
            return;
        }

        $this->eventInvalidator->addEvent($entity);

        if (!$entity->getFacebookEventId()) {
            return;
        }

        $entityManager = $args->getEntityManager();
        $exploration   = $entityManager->getRepository(Exploration::class)->find($entity->getFacebookEventId());

        if (!$exploration) {
            $exploration = (new Exploration())->setId($entity->getFacebookEventId());
        }

        $exploration
            ->setFirewallVersion(Firewall::VERSION)
            ->setLastUpdated($entity->getFbDateModification())
            ->setReason(Reject::EVENT_DELETED);

        $entityManager->persist($exploration);
    }
}
