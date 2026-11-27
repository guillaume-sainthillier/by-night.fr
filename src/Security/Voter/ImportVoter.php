<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\Voter;

use App\Entity\EventImport;
use App\Entity\User;
use Silarhi\VoterBundle\Attribute\Grants;

/**
 * A member's event import and its report and its files (the member's spreadsheet, the rows the check kept and refused)
 * are theirs alone: they hold their events' contacts and drafts. Administrators included, as for the member's other
 * private data.
 */
final class ImportVoter extends AbstractMemberVoter
{
    public const string VIEW = 'event_import.view';

    #[Grants(self::VIEW)]
    private function canView(EventImport $subject, User $user): bool
    {
        return null !== $user->getId() && $subject->getUser()?->getId() === $user->getId();
    }
}
