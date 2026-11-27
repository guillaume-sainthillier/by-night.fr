<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\Voter;

use App\Entity\EventExport;
use App\Entity\User;
use Silarhi\VoterBundle\Attribute\Grants;

/**
 * A member's event export and its file are theirs alone: they hold their events' contacts and drafts. Administrators
 * included, as for the member's other private data.
 */
final class ExportVoter extends AbstractMemberVoter
{
    public const string VIEW = 'event_export.view';

    #[Grants(self::VIEW)]
    private function canView(EventExport $subject, User $user): bool
    {
        return null !== $user->getId() && $subject->getUser()?->getId() === $user->getId();
    }
}
