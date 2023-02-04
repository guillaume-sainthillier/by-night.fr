<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use App\Dto\EventDto;
use App\Reject\Reject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EventConstraintValidator extends ConstraintValidator
{
    private bool $checkIfUpdate = false;

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function setUpdatabilityCkeck(bool $checkIfUpdate): void
    {
        $this->checkIfUpdate = $checkIfUpdate;
    }

    /**
     * @return void
     */
    public function validate($value, Constraint $constraint)
    {
        \assert($value instanceof EventDto);
        \assert($constraint instanceof EventConstraint);
        $reject = $value->reject;

        if ($reject && !$this->checkIfUpdate) {
            $reject->removeReason(Reject::NO_NEED_TO_UPDATE);
        }

        if (!$reject || $reject->isValid()) {
            return;
        }

        if ($reject->isEventDeleted()) {
            $this->context->buildViolation($constraint->eventDeleted)->addViolation();

            return;
        }

        $this->logger->error('Event is rejected', [
           'event' => (array) $value,
           'reject' => (array) $reject,
        ]);

        if ($reject->isBadEventName()) {
            $this->context->buildViolation($constraint->badEventName)->atPath('name')->addViolation();
        }

        if ($reject->isBadEventDate()) {
            $this->context->buildViolation($constraint->badEventDate)->atPath('shortcut')->addViolation();
        }

        if ($reject->isBadEventDateInterval()) {
            $this->context->buildViolation($constraint->badEventDateInterval)->atPath('shortcut')->addViolation();
        }

        if ($reject->isSpamEventDescription()) {
            $this->context->buildViolation($constraint->spamEventDescription)->atPath('description')->addViolation();
        }

        if ($reject->isBadEventDescription()) {
            $this->context->buildViolation($constraint->badEventDescrition)->atPath('description')->addViolation();
        }

        if ($reject->hasNoNeedToUpdate()) {
            $this->context->buildViolation($constraint->noNeedToUpdate)->addViolation();
        }

        if ($reject->hasNoPlaceProvided()) {
            $this->context->buildViolation($constraint->noPlaceProvided)->atPath('place')->addViolation();
        }

        if ($reject->hasNoPlaceLocationProvided()) {
            $this->context->buildViolation($constraint->noPlaceLocationProvided)->atPath('place')->addViolation();
        }

        if ($reject->isBadPlaceName()) {
            $this->context->buildViolation($constraint->badPlaceName)->atPath('place.name')->addViolation();
        }

        if ($reject->isBadPlaceLocation()) {
            $this->context->buildViolation($constraint->badPlaceLocation)->atPath('place.city.name')->addViolation();
        }

        if ($reject->isBadPlaceCityName()) {
            $this->context->buildViolation($constraint->badPlaceCityName)->atPath('place.city.name')->addViolation();
        }

        if ($reject->isBadPlaceCityPostalCode()) {
            $this->context->buildViolation($constraint->badPlacePostalCode)->atPath('place.city.postalCode')->addViolation();
        }

        if ($reject->hasNoCountryProvided()) {
            $this->context->buildViolation($constraint->noCountryProvided)->atPath('place.country')->addViolation();
        }

        if ($reject->isBadCountryName()) {
            $this->context->buildViolation($constraint->badCountryName)->atPath('place.country')->addViolation();
        }

        if (0 === \count($this->context->getViolations())) {
            $this->context->buildViolation("Une erreur de validité empêche l'événement d'être créé. Code d'erreur : " . $reject->getReason())->addViolation();
        }
    }
}
