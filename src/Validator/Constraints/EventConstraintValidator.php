<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use App\Entity\Event;
use App\Reject\Reject;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class EventConstraintValidator extends ConstraintValidator
{
    private RouterInterface $router;

    private bool $checkIfUpdate = false;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function setUpdatabilityCkeck(bool $checkIfUpdate): void
    {
        $this->checkIfUpdate = $checkIfUpdate;
    }

    /**
     * @param Event           $value
     * @param EventConstraint $constraint
     *
     * @return void
     */
    public function validate($value, Constraint $constraint)
    {
        \assert($value instanceof Event);
        \assert($constraint instanceof EventConstraint);
        $reject = $value->getReject();

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

        if ($reject->isBadEventName()) {
            $this->context->buildViolation($constraint->badEventName)->atPath('nom')->addViolation();
        }

        if ($reject->isBadEventDate()) {
            $this->context->buildViolation($constraint->badEventDate)->atPath('dateDebut')->addViolation();
        }

        if ($reject->isBadEventDateInterval()) {
            $this->context->buildViolation($constraint->badEventDateInterval)->atPath('dateDebut')->addViolation();
        }

        if ($reject->isSpamEventDescription()) {
            $this->context->buildViolation($constraint->spamEventDescription)->atPath('descriptif')->addViolation();
        }

        if ($reject->isBadEventDescription()) {
            $this->context->buildViolation($constraint->badEventDescrition)->atPath('descriptif')->addViolation();
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
            $this->context->buildViolation($constraint->badPlaceName)->atPath('placeName')->addViolation();
        }

        if ($reject->isBadPlaceLocation()) {
            $this->context->buildViolation($constraint->badPlaceLocation)->atPath('placeCity')->addViolation();
        }

        if ($reject->isBadPlaceCityName()) {
            $this->context->buildViolation($constraint->badPlaceCityName)->atPath('placeCity')->addViolation();
        }

        if ($reject->isBadPlaceCityPostalCode()) {
            $this->context->buildViolation($constraint->badPlacePostalCode)->atPath('placePostalCode')->addViolation();
        }

        if ($reject->hasNoCountryProvided()) {
            $this->context->buildViolation($constraint->noCountryProvided)->atPath('placeCountry')->addViolation();
        }

        if ($reject->isBadCountryName()) {
            $this->context->buildViolation($constraint->badCountryName)->atPath('placeCountry')->addViolation();
        }

        if ($reject->isBadUser()) {
            $link = $this->router->generate('app_event_details', [
                'slug' => $value->getSlug(),
                'id' => $value->getId(),
                'location' => $value->getLocationSlug(),
            ]);
            $message = str_replace([
                '[link]',
                '[/link]',
            ], [
                sprintf('<a href="%s">', $link),
                '</a>',
            ], $constraint->badUser);
            $this->context->buildViolation($message)->addViolation();
        }

        if (0 === \count($this->context->getViolations())) {
            $this->context->buildViolation("Une erreur de validité empêche l'événement d'être créé. Code d'erreur : " . $reject->getReason())->addViolation();
        }
    }
}
