<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    /**
     * @return void
     */
    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEnabled()) {
            $exception = new DisabledException('Votre compte a été désactivé par un administrateur.');
            $exception->setUser($user);
            throw $exception;
        }
    }

    /**
     * @return void
     */
    public function checkPostAuth(UserInterface $user)
    {
    }
}
