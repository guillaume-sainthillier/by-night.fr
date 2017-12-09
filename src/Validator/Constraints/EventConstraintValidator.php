<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 26/10/2016
 * Time: 22:24.
 */

namespace App\Validator\Constraints;

use App\Entity\Agenda;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use App\Reject\Reject;

class EventConstraintValidator extends ConstraintValidator
{
    /**
     * @var RouterInterface
     */
    private $router;

    private $checkIfUpdate;

    public function __construct(RouterInterface $router)
    {
        $this->router        = $router;
        $this->checkIfUpdate = true;
    }

    public function setUpdatabilityCkeck($checkIfUpdate)
    {
        $this->checkIfUpdate = $checkIfUpdate;
    }

    /**
     * @param Agenda $event
     * @param Constraint $constraint
     */
    public function validate($event, Constraint $constraint)
    {
        $reject = $event->getReject();

        /**
         * @var EventConstraint $constraint
         */
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

        if ($this->checkIfUpdate && $reject->hasNoNeedToUpdate()) {
            $this->context->buildViolation($constraint->noNeedToUpdate)->addViolation();
        }

        if ($reject->hasNoPlaceProvided()) {
            $this->context->buildViolation($constraint->noPlaceProvided)->atPath('place')->addViolation();
        }

        if ($reject->hasNoPlaceLocationProvided()) {
            $this->context->buildViolation($constraint->noPlaceLocationProvided)->atPath('place')->addViolation();
        }

        if ($reject->isBadPlaceName()) {
            $this->context->buildViolation($constraint->badPlaceName)->atPath('place.nom')->addViolation();
        }

        if ($reject->isBadPlaceLocation()) {
            $this->context->buildViolation($constraint->badPlaceLocation)->atPath('place.ville')->addViolation();
        }

        if ($reject->isBadPlaceCityName()) {
            $this->context->buildViolation($constraint->badPlaceCityName)->atPath('place.ville')->addViolation();
        }

        if ($reject->isBadPlaceCityPostalCode()) {
            $this->context->buildViolation($constraint->badPlacePostalCode)->atPath('place.codePostal')->addViolation();
        }

        if ($reject->isBadUser()) {
            $link = $this->router->generate('tbn_agenda_details', [
                'slug' => $event->getSlug(),
                'id'   => $event->getId(),
                'city' => $event->getPlace()->getCity()->getSlug(),
            ]);
            $message = \str_replace([
                '[link]',
                '[/link]',
            ], [
                \sprintf('<a href="%s">', $link),
                '</a>',
            ], $constraint->badUser);
            $this->context->buildViolation($message)->addViolation();
        }
    }
}
