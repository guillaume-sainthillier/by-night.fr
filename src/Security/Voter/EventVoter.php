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
use Silarhi\VoterBundle\Attribute\Grants;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Not a member's voter: a published event is seen by visitors too.
 */
final class EventVoter extends AbstractRuleVoter
{
    public const string CREATE = 'event.create';

    /** A published event is public; a draft is only seen by those who can edit it */
    public const string VIEW = 'event.view';

    public const string EDIT = 'event.edit';

    public const string DELETE = 'event.delete';

    public function __construct(private readonly Security $security)
    {
    }

    #[Grants(self::CREATE)]
    private function canCreate(TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->security->isGranted('ROLE_ADMIN') || ($user->isEnabled() && $user->isVerified());
    }

    #[Grants(self::VIEW)]
    private function canView(Event $subject, TokenInterface $token): bool
    {
        // An event its source no longer lists is a draft for the listings only: its page stays open
        if (!$subject->isDraft() || $subject->isRemovedAtSource()) {
            return true;
        }

        return $this->canEdit($subject, $token);
    }

    #[Grants(self::EDIT)]
    #[Grants(self::DELETE)]
    private function canEdit(Event $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->security->isGranted('ROLE_ADMIN') || ($subject->getPublisher()?->isManagedBy($user) ?? false);
    }
}
