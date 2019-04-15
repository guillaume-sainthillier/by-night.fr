<?php

namespace App\EventListener;

use App\Entity\Agenda;
use App\Entity\Calendrier;
use App\Entity\City;
use App\Entity\User;
use App\Invalidator\TagsInvalidator;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EntityTagger
{
    /**
     * @var TagsInvalidator
     */
    private $eventInvalidator;

    public function __construct(TagsInvalidator $eventInvalidator)
    {
        $this->eventInvalidator = $eventInvalidator;
    }

    public function postFlush()
    {
        $this->eventInvalidator->flush();
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        if ($entity instanceof City) {
            $this->eventInvalidator->addCity($entity);
        } elseif ($entity instanceof Agenda && $entity->getPlace()) {
            $this->eventInvalidator->addPlace($entity->getPlace());
        }
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();


        //Flag old place too in cause it has changed
        if ($entity instanceof Agenda && $entity->getPlace()) {
            $this->eventInvalidator->addPlace($entity->getPlace());
        }
    }

    public function postUpdate(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->tag($entity);
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();
        $this->tag($entity);
    }

    private function tag($entity)
    {
        if ($entity instanceof User) {
            $this->eventInvalidator->addUser($entity);
        } elseif ($entity instanceof City) {
            $this->eventInvalidator->addCity($entity);
        } elseif ($entity instanceof Agenda) {
            $this->eventInvalidator->addEvent($entity);
        } elseif ($entity instanceof Calendrier) {
            $this->eventInvalidator->addCalendrier($entity);
        }
    }
}
