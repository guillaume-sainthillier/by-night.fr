<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use LogicException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;

class EventVoter extends Voter
{
    /**
     * @var string
     */
    public const EDIT = 'event.edit';

    /**
     * @var string
     */
    public const DELETE = 'event.delete';

    public function __construct(private Security $security)
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return match ($attribute) {
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            default => throw new LogicException('This code should not be reached!'),
        };
    }

    private function canEdit(Event $event, User $user): bool
    {
        return $event->getUser() === $user;
    }

    private function canDelete(Event $event, User $user): bool
    {
        return $this->canEdit($event, $user);
    }

    /**
     * {@inheritDoc}
     */
    protected function supports(string $attribute, $subject)
    {
        return \in_array($attribute, [
            self::EDIT,
            self::DELETE,
        ], true);
    }
}
