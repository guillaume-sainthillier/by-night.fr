<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Validator\Constraints;

use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute]
class EventConstraint extends Constraint
{
    public string $badEventName = "Le nom de l'événement est incorrect.";

    public string $badEventDate = "Les date de début et de fin de l'événement doivent être remplies.";

    public string $badEventDateInterval = 'La date de fin doit être supérieure ou égale à la date de début.';

    public string $spamEventDescription = "La description de l'événement est considérée comme du spam ou à caractère commercial.";

    public string $badEventDescrition = "La description de l'événement est incorrecte.";

    public string $noNeedToUpdate = "L'événement n'a pas besoin d'être mis à jour.";

    public string $noPlaceProvided = "Le lieu de l'événement n'est pas rempli.";

    public string $noPlaceLocationProvided = "L'endroit du lieu de l'événement n'est pas rempli.";

    public string $badPlaceName = 'Le nom du lieu est incorrect.';

    public string $badPlaceLocation = "Le lieu de l'événement n'est pas compris dans la liste des lieux autorisés.";

    public string $badPlaceCityName = "La ville de l'événement est inconnue dans le pays.";

    public string $badPlacePostalCode = 'Le code postal du lieu est incorrect.';

    public string $noCountryProvided = "Le pays de l'événement est obligatoire.";

    public string $badCountryName = "Le pays de l'événement n'est pas encore supporté par notre plateforme.";

    public string $badUser = 'Un [link]événement[/link] similaire au vôtre a déjà été créé sur la plateforme.';

    public string $eventDeleted = "L'événement facebook a été supprimé par son créateur. Il ne peut plus être mis à jour sur la plateforme.";

    public function getTargets(): string|array
    {
        // This is the important bit.
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy(): string
    {
        return static::class . 'Validator';
    }
}
