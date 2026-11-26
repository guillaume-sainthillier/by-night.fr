<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\Voter;

use App\Entity\Publisher;
use App\Entity\User;
use Silarhi\VoterBundle\Attribute\Grants;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * A publisher's organizer pages (its events, imports, exports, its page) are the members' who manage it
 * (Publisher::isManagedBy()), and the administrators', as its events are (EventVoter).
 */
final class PublisherVoter extends AbstractMemberVoter
{
    public const string EDIT = 'publisher.edit';

    public function __construct(private readonly Security $security)
    {
    }

    #[Grants(self::EDIT)]
    private function canEdit(Publisher $subject, User $user): bool
    {
        return $subject->isManagedBy($user) || $this->security->isGranted('ROLE_ADMIN');
    }
}
