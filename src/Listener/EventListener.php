<?php

namespace AppBundle\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use AppBundle\Entity\Agenda;
use AppBundle\Invalidator\EventInvalidator;
use AppBundle\Entity\Exploration;
use AppBundle\Reject\Reject;
use AppBundle\Utils\Firewall;
use AppBundle\Entity\User;

class EventListener
{
    /**
     * @var EventInvalidator
     */
    private $eventInvalidator;

    /**
     * @var bool
     */
    private $debug;

    public function __construct(EventInvalidator $eventInvalidator, $debug)
    {
        $this->eventInvalidator = $eventInvalidator;
        $this->debug            = $debug;
    }

    public function postFlush()
    {
        $this->eventInvalidator->invalidateEvents();
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        if ($this->debug) {
            return;
        }

        $entity = $args->getEntity();

        if ($entity instanceof User) {
            $this->eventInvalidator->addUser($entity);

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
        if (!$this->debug) {
            if ($entity instanceof User) {
                $this->eventInvalidator->addUser($entity);

                return;
            }

            if (!$entity instanceof Agenda) {
                return;
            }

            $this->eventInvalidator->addEvent($entity);
        }

        if (!$entity->getFacebookEventId()) {
            return;
        }

        $entityManager = $args->getEntityManager();
        $exploration   = $entityManager->getRepository('AppBundle:Exploration')->find($entity->getFacebookEventId());

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
