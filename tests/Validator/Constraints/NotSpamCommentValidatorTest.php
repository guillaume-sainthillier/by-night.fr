<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Validator\Constraints;

use App\Validator\Constraints\NotSpamComment;
use App\Validator\Constraints\NotSpamCommentValidator;
use Override;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<NotSpamCommentValidator>
 */
final class NotSpamCommentValidatorTest extends ConstraintValidatorTestCase
{
    #[Override]
    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NotSpamCommentValidator();
    }

    public function testASpamCommentIsRefused(): void
    {
        $constraint = new NotSpamComment();
        $this->validator->validate('Offre de prêt entre particuliers, contact : preteur@example.com', $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAGenuineCommentPasses(): void
    {
        $this->validator->validate('Super soirée, merci aux organisateurs !', new NotSpamComment());

        $this->assertNoViolation();
    }

    public function testAnEmptyCommentIsLeftToNotBlank(): void
    {
        $this->validator->validate(null, new NotSpamComment());

        $this->assertNoViolation();
    }
}
