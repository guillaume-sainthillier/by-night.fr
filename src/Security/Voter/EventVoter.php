<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\Voter;

use App\Entity\Event;
use App\Entity\User;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Event>
 */
final class EventVoter extends Voter
{
    public const string CREATE = 'event.create';

    /** A published event is public; a draft is only seen by those who can edit it */
    public const string VIEW = 'event.view';

    public const string EDIT = 'event.edit';

    public const string DELETE = 'event.delete';

    public function __construct(private readonly Security $security)
    {
    }

    /**
     * {@inheritDoc}
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // An event its source no longer lists is a draft for the listings only: its page stays open
        if (self::VIEW === $attribute && (!$subject->isDraft() || $subject->isRemovedAtSource())) {
            return true;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return match ($attribute) {
            self::CREATE => $this->canCreate($user),
            self::VIEW, self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            default => throw new LogicException('This code should not be reached!'),
        };
    }

    private function canCreate(User $user): bool
    {
        return $user->isEnabled() && $user->isVerified();
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
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [
            self::CREATE,
            self::VIEW,
            self::EDIT,
            self::DELETE,
        ], true);
    }
}
