<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use App\Utils\SpamDetector;
use Override;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class NotSpamCommentValidator extends ConstraintValidator
{
    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotSpamComment) {
            throw new UnexpectedTypeException($constraint, NotSpamComment::class);
        }

        if (\is_string($value) && SpamDetector::isSpamComment($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
