<?php
/**
 * Created by PhpStorm.
 * User: guillaume
 * Date: 20/04/2017
 * Time: 21:47.
 */

namespace AppBundle\SEO;

use AppBundle\Entity\Agenda;
use AppBundle\Utils\Util;

class EventSEO
{
    public function getEventDate(Agenda $event)
    {
        if (!$event->getDateFin() || $event->getDateDebut() === $event->getDateFin()) {
            return sprintf('le %s',
                $this->formatDate($event->getDateDebut(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE)
            );
        }

        return sprintf('du %s au %s',
            $this->formatDate($event->getDateDebut(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE),
            $this->formatDate($event->getDateFin(), \IntlDateFormatter::FULL, \IntlDateFormatter::NONE)
        );
    }

    public function getEventDescription(Agenda $agenda)
    {
        $description = sprintf('Découvrez %s.', $agenda->getNom());

        if ($agenda->getPlace()) {
            $description .= sprintf(' %s à %s.',
                $agenda->getPlace()->getNom(),
                $agenda->getPlace()->getVille()
            );
        }

        $description .= sprintf(' %s.', ucfirst($this->getEventDateTime($agenda)));

        $tags = $agenda->getDistinctTags();

        if (count($tags)) {
            $description .= sprintf(' %s.', implode(', ', $tags));
        }

        if ($agenda->getFbParticipations() + $agenda->getFbInterets() > 50) {
            $description .= sprintf(' %d personnes intéressées', $agenda->getFbParticipations() + $agenda->getFbInterets());
        }

        return $description;
    }

    public function getEventDateTime(Agenda $event)
    {
        $datetime = $this->getEventDate($event);

        if ($event->getHoraires()) {
            $datetime .= sprintf(' - %s', $event->getHoraires());
        }

        $datetime = trim($datetime);

        return trim($datetime);
    }

    public function getEventShortTitle(Agenda $event)
    {
        $shortTitle = $event->getNom();
        if ($event->getModificationDerniereMinute()) {
            $shortTitle .= sprintf(' [%s]', $event->getModificationDerniereMinute());
        }

        return $shortTitle;
    }

    public function getEventFullTitle(Agenda $event)
    {
        $title = $this->getEventShortTitle($event);

        if ($event->getPlace()) {
            $title .= sprintf(' - %s', $event->getPlace()->getNom());
        }

        return $title;
    }

    private function formatDate(\DateTime $date, $dateFormat, $timeFormat)
    {
        $formatter = \IntlDateFormatter::create(null, $dateFormat, $timeFormat);

        return $formatter->format($date->getTimestamp());
    }
}
