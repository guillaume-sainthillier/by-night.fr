<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

final class ReCaptchaResponse extends Constraint
{
    public string $message = 'Le captcha est incorrect.';

    public function validatedBy(): string
    {
        return self::class . 'Validator';
    }
}
