<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use App\Captcha\CaptchaWrapper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class ReCaptchaResponseValidator extends ConstraintValidator
{
    public function __construct(private readonly RequestStack $requestStack, private readonly CaptchaWrapper $captcha)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        \assert($constraint instanceof ReCaptchaResponse);

        $value ??= $this->requestStack->getCurrentRequest()->request->get('g-recaptcha-response');

        $isValid = $this->captcha->verify($value);
        if (!$isValid) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
