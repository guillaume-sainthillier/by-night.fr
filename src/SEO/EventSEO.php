<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
    public function getEventDescription(Event $event): string
    {
        $description = \sprintf('Découvrez %s.', $event->getName());

        if ($event->getPlaceName() && $event->getPlaceCity()) {
            $description .= \sprintf(' %s à %s.',
                $event->getPlaceName(),
                $event->getPlaceCity()
            );
        }

        $description .= \sprintf(' %s.', ucfirst($this->getEventDateTime($event)));

        $tags = $event->getDistinctTags();

        if ([] !== $tags) {
            $description .= \sprintf(' %s.', implode(', ', array_keys($tags)));
        }

        if ($event->getFbParticipations() + $event->getFbInterets() > 50) {
            $description .= \sprintf(' %d personnes intéressées', $event->getFbParticipations() + $event->getFbInterets());
        }

        return $description;
    }

    public function getEventDateTime(Event $event): string
    {
        $datetime = $this->getEventDate($event);

        if ($event->getHours()) {
            $datetime .= \sprintf(' - %s', $event->getHours());
        }

        return trim($datetime);
    }

    public function getEventDate(Event $event): string
    {
        if (!$event->getEndDate() || $event->getStartDate() === $event->getEndDate()) {
            return \sprintf('le %s',
                $this->formatDate($event->getStartDate(), IntlDateFormatter::FULL, IntlDateFormatter::NONE)
            );
        }

        return \sprintf('du %s au %s',
            $this->formatDate($event->getStartDate(), IntlDateFormatter::FULL, IntlDateFormatter::NONE),
            $this->formatDate($event->getEndDate(), IntlDateFormatter::FULL, IntlDateFormatter::NONE)
        );
    }

    private function formatDate(DateTimeInterface $date, int $dateFormat, int $timeFormat): string
    {
        $formatter = IntlDateFormatter::create(null, $dateFormat, $timeFormat);

        return $formatter->format($date->getTimestamp());
    }

    public function getEventFullTitle(Event $event): ?string
    {
        $title = $this->getEventShortTitle($event);

        if ($event->getPlaceName()) {
            $title .= \sprintf(' - %s', $event->getPlaceName());
        }

        return $title;
    }

    public function getEventShortTitle(Event $event): ?string
    {
        $shortTitle = $event->getName();
        if ($event->getStatus()) {
            $shortTitle = \sprintf('[%s] %s', $event->getStatus(), $shortTitle);
        }

        return $shortTitle;
    }
}
