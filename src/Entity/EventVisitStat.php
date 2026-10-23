<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\EventVisitStatRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The audience of an event page on a day (Paris time), read from Umami by `app:events:sync-visits`
 * (App\Analytics\EventVisitSync): its visitors, a visitor counted once a day, and the clicks on its ticketing links.
 * Shown to the event's author in their personal space. The 90 days before Umami (to 2026-10-22) were read once from
 * Google Analytics, which only saw the visitors who accepted its cookies: those rows are flagged fromGoogleAnalytics.
 */
#[ORM\Entity(repositoryClass: EventVisitStatRepository::class)]
#[ORM\UniqueConstraint(name: 'event_visit_stat_unique', columns: ['event_id', 'day'])]
#[ORM\Index(name: 'event_visit_stat_day_idx', columns: ['day'])]
class EventVisitStat
{
    use EntityIdentityTrait;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Event::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Event $event,

        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private DateTimeImmutable $day,

        #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
        private int $visitors = 0,

        #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
        private int $ticketClicks = 0,

        #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
        private bool $fromGoogleAnalytics = false,
    ) {
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getDay(): DateTimeImmutable
    {
        return $this->day;
    }

    public function getVisitors(): int
    {
        return $this->visitors;
    }

    public function getTicketClicks(): int
    {
        return $this->ticketClicks;
    }

    public function isFromGoogleAnalytics(): bool
    {
        return $this->fromGoogleAnalytics;
    }
}
