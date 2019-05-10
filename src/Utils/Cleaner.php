<?php

namespace App\Utils;

use App\Entity\Event;
use App\Entity\Place;
use DateTime;

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

    public function cleanEvent(Event $event)
    {
        if (!$event->getDateFin() instanceof DateTime) {
            $event->setDateFin($event->getDateDebut());
        }

        $event->setNom($this->clean($event->getNom()) ?: null)
            ->setDescriptif($this->clean($event->getDescriptif()) ?: null)
            ->setReservationEmail(\substr($event->getReservationEmail(), 0, 255) ?: null)
            ->setReservationTelephone(\substr($event->getReservationTelephone(), 0, 255) ?: null)
            ->setReservationInternet(\substr($event->getReservationInternet(), 0, 512) ?: null)
            ->setAdresse(\substr($event->getAdresse(), 0, 255) ?: null)
            ->setCategorieManifestation(\substr($event->getCategorieManifestation(), 0, 128) ?: null)
            ->setThemeManifestation(\substr($event->getThemeManifestation(), 0, 128) ?: null)
            ->setTypeManifestation(\substr($event->getTypeManifestation(), 0, 128) ?: null)
            ->setHoraires(\substr($event->getHoraires(), 0, 255) ?: null);
    }

    public function cleanPlace(Place $place)
    {
        $place->setNom($this->cleanNormalString($place->getNom()) ?: null)
            ->setRue($this->cleanNormalString($place->getRue()) ?: null)
            ->setLatitude((float)($this->util->replaceNonNumericChars($place->getLatitude())) ?: null)
            ->setLongitude((float)($this->util->replaceNonNumericChars($place->getLongitude())) ?: null)
            ->setVille($this->cleanPostalString($place->getVille()) ?: null)
            ->setCodePostal($this->util->replaceNonNumericChars($place->getCodePostal()) ?: null);
    }

    private function clean($string)
    {
        return \trim($string);
    }

    private function cleanString($string, $delimiters = [])
    {
        $step1 = $this->util->utf8TitleCase($string);
        $step2 = $this->util->deleteMultipleSpaces($step1);
        $step3 = $this->util->deleteSpaceBetween($step2, $delimiters);

        return \trim($step3);
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
