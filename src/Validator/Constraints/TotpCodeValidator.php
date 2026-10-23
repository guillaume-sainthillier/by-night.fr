<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use App\Entity\User;
use App\Manager\Security\TwoFactorSetupManager;
use Override;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class TotpCodeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly Security $security,
        private readonly GoogleAuthenticatorInterface $googleAuthenticator,
        private readonly TwoFactorSetupManager $setupManager,
    ) {
    }

    #[Override]
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof TotpCode) {
            throw new UnexpectedTypeException($constraint, TotpCode::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $user = $this->security->getUser();
        if (!\is_string($value) || !$user instanceof User || !$this->googleAuthenticator->checkCode($this->setupManager->getPendingTotp($user), $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
