<?php

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EventVoter extends Voter
{
    const EDIT = 'edit';
    const DELETE = 'delete';

    private function canEdit(Event $event, User $user)
    {
        if ($event->getUser() === $user) {
            return true;
        }

        return false;
    }

    private function canDelete(Event $event, User $user)
    {
        return $this->canEdit($event, $user);
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($subject, $user);
            case self::DELETE:
                return $this->canDelete($subject, $user);
        }

        throw new \LogicException('This code should not be reached!');
    }

    protected function supports($attribute, $subject)
    {
        return $subject instanceof Event;
    }
}
