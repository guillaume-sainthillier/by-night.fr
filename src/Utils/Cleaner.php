<?php

namespace App\Utils;

use App\Entity\Event;
use App\Entity\Place;

class Cleaner
{
    private $util;

    public function __construct(Util $util)
    {
        $this->util = $util;
    }

    public function cleanEvent(Event $event)
    {
        if (!$event->getDateFin() instanceof \DateTimeInterface) {
            $event->setDateFin($event->getDateDebut());
        }

        $event->setNom($this->clean($event->getNom()) ?: null)
            ->setDescriptif($this->clean($event->getDescriptif()) ?: null)
            ->setReservationEmail(\mb_substr($event->getReservationEmail(), 0, 255) ?: null)
            ->setReservationTelephone(\mb_substr($event->getReservationTelephone(), 0, 255) ?: null)
            ->setReservationInternet(\mb_substr($event->getReservationInternet(), 0, 512) ?: null)
            ->setAdresse(\mb_substr($event->getAdresse(), 0, 255) ?: null)
            ->setCategorieManifestation(\mb_substr($event->getCategorieManifestation(), 0, 128) ?: null)
            ->setThemeManifestation(\mb_substr($event->getThemeManifestation(), 0, 128) ?: null)
            ->setTypeManifestation(\mb_substr($event->getTypeManifestation(), 0, 128) ?: null)
            ->setHoraires(\mb_substr($event->getHoraires(), 0, 255) ?: null);
    }

    private function clean($string)
    {
        return \trim($string);
    }

    public function cleanPlace(Place $place)
    {
        $place->setNom($this->cleanNormalString($place->getNom()) ?: null)
            ->setRue($this->cleanNormalString($place->getRue()) ?: null)
            ->setLatitude((float) ($this->util->replaceNonNumericChars($place->getLatitude())) ?: null)
            ->setLongitude((float) ($this->util->replaceNonNumericChars($place->getLongitude())) ?: null)
            ->setVille($this->cleanPostalString($place->getVille()) ?: null)
            ->setCodePostal($this->util->replaceNonNumericChars($place->getCodePostal()) ?: null);
    }

    private function cleanNormalString($string)
    {
        return $this->cleanString($string, '');
    }

    private function cleanString($string, $delimiters = [])
    {
        $step1 = $this->util->utf8TitleCase($string);
        $step2 = $this->util->deleteMultipleSpaces($step1);
        $step3 = $this->util->deleteSpaceBetween($step2, $delimiters);

        return \trim($step3);
    }

    private function cleanPostalString($string)
    {
        return $this->cleanString($string, ['-']);
    }
}
