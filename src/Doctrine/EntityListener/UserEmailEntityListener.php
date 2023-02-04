<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Doctrine\EntityListener;

use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

class UserEmailEntityListener implements EventSubscriberInterface
{
    /** @var User[] */
    private array $queue = [];

    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    public function prePersist(User $user): void
    {
        $this->handleUserEmail($user);
    }

    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        if (!$args->hasChangedField('email')) {
            return;
        }

        $this->handleUserEmail($user);
    }

    public function postFlush(): void
    {
        if ([] === $this->queue) {
            return;
        }

        foreach ($this->queue as $user) {
            $this->emailVerifier->sendEmailConfirmation($user);
        }

        unset($this->queue); // calls gc
        $this->queue = [];
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postFlush,
        ];
    }

    private function handleUserEmail(User $user): void
    {
        if (!$user->isFromLogin()) {
            return;
        }

        $user->setVerified(false);
        $this->queue[] = $user;
    }
}
