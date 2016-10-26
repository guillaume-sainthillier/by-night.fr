<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/10/2016
 * Time: 22:24
 */

namespace TBN\UserBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use TBN\UserBundle\Captcha\CaptchaWrapper;

class ReCaptchaResponseValidator extends ConstraintValidator
{
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