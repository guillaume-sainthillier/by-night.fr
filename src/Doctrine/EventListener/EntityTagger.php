<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EventListener;

use App\Entity\City;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Entity\UserEvent;
use App\Invalidator\TagsInvalidator;
use Doctrine\ORM\Event\LifecycleEventArgs;

class EntityTagger
{
    public function __construct(private TagsInvalidator $eventInvalidator)
    {
    }

    public function postFlush(): void
    {
        $this->eventInvalidator->flush();
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        if ($entity instanceof City) {
            $this->tag($entity);
        } elseif ($entity instanceof Event && $entity->getPlace()) {
            $this->tag($entity->getPlace());
        }
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();

        //Flag old place too in case it has changed
        if ($entity instanceof Event && $entity->getPlace()) {
            $this->tag($entity->getPlace());
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();
        $this->tag($entity);
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getEntity();
        $this->tag($entity);
    }

    private function tag(?object $entity): void
    {
        if ($entity instanceof User) {
            $this->eventInvalidator->addUser($entity);
        } elseif ($entity instanceof City) {
            $this->eventInvalidator->addCity($entity);
        } elseif ($entity instanceof Event) {
            $this->eventInvalidator->addEvent($entity);
        } elseif ($entity instanceof UserEvent) {
            $this->eventInvalidator->addUserEvent($entity);
        } elseif ($entity instanceof Place) {
            $this->eventInvalidator->addPlace($entity);
        }
    }
}
