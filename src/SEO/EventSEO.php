<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SEO;

use App\Entity\Event;
use DateTimeInterface;
use IntlDateFormatter;

class EventSEO
{
    public function getEventDescription(Event $event)
    {
        $description = sprintf('Découvrez %s.', $event->getNom());

        if ($event->getPlaceName() && $event->getPlaceCity()) {
            $description .= sprintf(' %s à %s.',
                $event->getPlaceName(),
                $event->getPlaceCity()
            );
        }

        $description .= sprintf(' %s.', ucfirst($this->getEventDateTime($event)));

        $tags = $event->getDistinctTags();

        if ((is_countable($tags) ? \count($tags) : 0) > 0) {
            $description .= sprintf(' %s.', implode(', ', $tags));
        }

        if ($event->getFbParticipations() + $event->getFbInterets() > 50) {
            $description .= sprintf(' %d personnes intéressées', $event->getFbParticipations() + $event->getFbInterets());
        }

        return $description;
    }

    public function getEventDateTime(Event $event)
    {
        $datetime = $this->getEventDate($event);

        if ($event->getHoraires()) {
            $datetime .= sprintf(' - %s', $event->getHoraires());
        }

        $datetime = trim($datetime);

        return trim($datetime);
    }

    public function getEventDate(Event $event)
    {
        if (!$event->getDateFin() || $event->getDateDebut() === $event->getDateFin()) {
            return sprintf('le %s',
                $this->formatDate($event->getDateDebut(), IntlDateFormatter::FULL, IntlDateFormatter::NONE)
            );
        }

        return sprintf('du %s au %s',
            $this->formatDate($event->getDateDebut(), IntlDateFormatter::FULL, IntlDateFormatter::NONE),
            $this->formatDate($event->getDateFin(), IntlDateFormatter::FULL, IntlDateFormatter::NONE)
        );
    }

    private function formatDate(DateTimeInterface $date, $dateFormat, $timeFormat)
    {
        $formatter = IntlDateFormatter::create(null, $dateFormat, $timeFormat);

        return $formatter->format($date->getTimestamp());
    }

    public function getEventFullTitle(Event $event)
    {
        $title = $this->getEventShortTitle($event);

        if ($event->getPlaceName()) {
            $title .= sprintf(' - %s', $event->getPlaceName());
        }

        return $title;
    }

    public function getEventShortTitle(Event $event)
    {
        $shortTitle = $event->getNom();
        if ($event->getModificationDerniereMinute()) {
            $shortTitle = sprintf('[%s] %s', $event->getModificationDerniereMinute(), $shortTitle);
        }

        return $shortTitle;
    }
}
