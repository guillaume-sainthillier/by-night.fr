<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security\Voter;

use App\Entity\User;
use Override;
use Silarhi\VoterBundle\Attribute\ProvidesArgument;
use Silarhi\VoterBundle\Voter\AbstractRuleVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The voters whose rules are a member's: a visitor is denied before any rule, and the rules ask for the member as a
 * `User` parameter.
 */
#[ProvidesArgument(User::class)]
abstract class AbstractMemberVoter extends AbstractRuleVoter
{
    #[Override]
    protected function resolveContext(TokenInterface $token): ?array
    {
        $user = $token->getUser();
        $context = parent::resolveContext($token);

        return $user instanceof User && null !== $context ? [User::class => $user] + $context : null;
    }
}
