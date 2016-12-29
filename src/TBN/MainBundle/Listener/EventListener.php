<?php

namespace TBN\MainBundle\Listener;


use Doctrine\ORM\Event\LifecycleEventArgs;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\MainBundle\Invalidator\EventInvalidator;
use TBN\MajDataBundle\Entity\Exploration;
use TBN\MajDataBundle\Reject\Reject;
use TBN\MajDataBundle\Handler\DoctrineEventHandler;
use TBN\MajDataBundle\Utils\Firewall;

class EventListener
{
    private $eventInvalidator;

    public function __construct(EventInvalidator $eventInvalidator)
    {
        $this->eventInvalidator = $eventInvalidator;
    }

    public function postUpdate(LifecycleEventArgs $args) {
        $entity = $args->getEntity();

        if (!$entity instanceof Agenda) {
            return;
        }

        $this->eventInvalidator->invalidateEvent($entity);
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if (!$entity instanceof Agenda) {
            return;
        }

        $this->eventInvalidator->invalidateEvent($entity);

        if(!$entity->getFacebookEventId()) {
            return;
        }

        $entityManager = $args->getEntityManager();
        $exploration = $entityManager->getRepository('TBNMajDataBundle:Exploration')->findOneBy([
           'id' => $entity->getFacebookEventId()
        ]);

        if(! $exploration) {
            $exploration = (new Exploration)->setId($entity->getFacebookEventId());
        }

        $exploration
            ->setFirewallVersion(Firewall::VERSION)
            ->setLastUpdated($entity->getFbDateModification())
            ->setReason(Reject::EVENT_DELETED)
        ;

        $entityManager->persist($exploration);
    }
}
