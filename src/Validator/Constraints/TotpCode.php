<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The current code of the authenticator app the logged-in user is setting up (App\Manager\Security\TwoFactorSetupManager).
 * Rate limit the submissions before reading the form's validity: this constraint would otherwise let a 6-digit code be
 * guessed.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class TotpCode extends Constraint
{
    public string $message = "Ce code n'est pas le bon : saisissez celui que votre application affiche en ce moment.";
}
