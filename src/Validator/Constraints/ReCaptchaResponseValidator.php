<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use App\Captcha\CaptchaWrapper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ReCaptchaResponseValidator extends ConstraintValidator
{
    private RequestStack $requestStack;
    private CaptchaWrapper $captcha;

    public function __construct(RequestStack $requestStack, CaptchaWrapper $captcha)
    {
        $this->requestStack = $requestStack;
        $this->captcha = $captcha;
    }

    public function validate($value, Constraint $constraint)
    {
        $value ??= $this->requestStack->getCurrentRequest()->request->get('g-recaptcha-response');

        $isValid = $this->captcha->verify($value);
        if (!$isValid) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
