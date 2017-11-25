<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Place;
use AppBundle\Entity\Agenda;

/**
 * Description of Merger.
 *
 * @author Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 */
class Cleaner
{
    private $util;

    public function __construct(Util $util)
    {
        $this->util = $util;
    }

    public function cleanEvent(Agenda $agenda)
    {
        if (!$agenda->getDateFin() instanceof \DateTime) {
            $agenda->setDateFin($agenda->getDateDebut());
        }

        $agenda->setNom($this->clean($agenda->getNom()) ?: null)
            ->setDescriptif($this->clean($agenda->getDescriptif()) ?: null)
            ->setReservationEmail(substr($agenda->getReservationEmail(), 0, 255) ?: null)
            ->setReservationTelephone(substr($agenda->getReservationTelephone(), 0, 255) ?: null)
            ->setReservationInternet(substr($agenda->getReservationInternet(), 0, 512) ?: null)
            ->setAdresse(substr($agenda->getAdresse(), 0, 255) ?: null)
            ->setCategorieManifestation(substr($agenda->getCategorieManifestation(), 0, 128) ?: null)
            ->setThemeManifestation(substr($agenda->getThemeManifestation(), 0, 128) ?: null)
            ->setTypeManifestation(substr($agenda->getTypeManifestation(), 0, 128) ?: null)
            ->setHoraires(substr($agenda->getHoraires(), 0, 255) ?: null);
    }

    public function cleanPlace(Place $place)
    {
        $place->setNom($this->cleanNormalString($place->getNom()) ?: null)
            ->setRue($this->cleanNormalString($place->getRue()) ?: null)
            ->setLatitude(floatval($this->util->replaceNonNumericChars($place->getLatitude())) ?: null)
            ->setLongitude(floatval($this->util->replaceNonNumericChars($place->getLongitude())) ?: null)
            ->setVille($this->cleanPostalString($place->getVille()) ?: null)
            ->setCodePostal($this->util->replaceNonNumericChars($place->getCodePostal()) ?: null);
    }

    private function clean($string)
    {
        return trim($string);
    }

    private function cleanString($string, $delimiters = [])
    {
        $step1 = $this->util->utf8TitleCase($string);
        $step2 = $this->util->deleteMultipleSpaces($step1);
        $step3 = $this->util->deleteSpaceBetween($step2, $delimiters);

        return trim($step3);
    }

    private function cleanNormalString($string)
    {
        return $this->cleanString($string, '');
    }

    private function cleanPostalString($string)
    {
        return $this->cleanString($string, ['-']);
    }
}
