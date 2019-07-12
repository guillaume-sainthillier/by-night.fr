<?php

namespace App\Validator\Constraints;

use App\Captcha\CaptchaWrapper;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ReCaptchaResponseValidator extends ConstraintValidator
{
    /** @var CaptchaWrapper */
    private $captcha;

    public function __construct(CaptchaWrapper $captcha)
    {
        $this->captcha = $captcha;
    }

    public function validate($value, Constraint $constraint)
    {
        $isValid = $this->captcha->verify($value);
        if (!$isValid) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
