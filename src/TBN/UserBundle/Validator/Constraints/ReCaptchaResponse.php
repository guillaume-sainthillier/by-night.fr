<?php

namespace TBN\UserBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class ReCaptchaResponse extends Constraint
{
    public $message = 'Le captcha est incorrect.';

    public function validatedBy()
    {
        return get_class($this).'Validator';
    }
}
