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
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A member's event import and its report and its files (the member's spreadsheet, the rows the check kept and refused) are theirs alone: they hold their events' contacts and drafts. Administrators
 * included, as for the member's other private data.
 *
 * @extends Voter<string, EventImport>
 */
final class ImportVoter extends Voter
{
    public const string VIEW = 'event_import.view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof EventImport;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && null !== $user->getId() && $subject->getUser()?->getId() === $user->getId();
    }
}
