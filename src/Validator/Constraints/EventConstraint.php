<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class EventConstraint extends Constraint
{
    public $badEventName = "Le nom de l'événément est incorrect.";

    public $badEventDate = "Les date de début et de fin de l'événement doivent être remplies.";

    public $badEventDateInterval = 'La date de fin doit être supérieure ou égale à la date de début.';

    public $spamEventDescription = "La description de l'événement est considérée comme du spam ou à caractère commercial.";

    public $badEventDescrition = "La description de l'événement est incorrecte.";

    public $noNeedToUpdate = "L'événement n'a pas besoin d'être mis à jour.";

    public $noPlaceProvided = "Le lieu de l'événement n'est pas rempli.";

    public $noPlaceLocationProvided = "L'endroit du lieu de l'événement n'est pas rempli.";

    public $badPlaceName = 'Le nom du lieu est incorrect.';

    public $badPlaceLocation = "Le lieu de l'événément n'est pas compris dans la liste des lieux autorisés.";

    public $badPlaceCityName = 'unused.';

    public $badPlacePostalCode = 'Le code postal du lieu est incorrect.';

    public $badUser = 'Un [link]événément[/link] similaire au vôtre a déjà été créé sur la plateforme.';

    public $eventDeleted = "L'événement facebook a été supprimé par son créateur. Il ne peut plus être mis à jour sur la plateforme.";

    public function getTargets()
    {
        // This is the important bit.
        return self::CLASS_CONSTRAINT;
    }

    public function validatedBy()
    {
        return \get_class($this) . 'Validator';
    }
}
