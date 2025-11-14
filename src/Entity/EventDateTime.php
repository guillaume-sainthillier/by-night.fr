<?php

/*
 * This file is part of By Night.
 * (c) 2013-2025 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\EventDateTimeRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventDateTimeRepository::class)]
#[ORM\Table(name: 'event_date_time')]
#[ORM\Index(columns: ['start_date_time'], name: 'event_date_time_start_idx')]
#[ORM\Index(columns: ['end_date_time'], name: 'event_date_time_end_idx')]
#[ORM\Index(columns: ['event_id', 'start_date_time'], name: 'event_date_time_event_start_idx')]
class EventDateTime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'dateTimes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $startDateTime = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTime $endDateTime = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getStartDateTime(): ?DateTime
    {
        return $this->startDateTime;
    }

    public function setStartDateTime(?DateTime $startDateTime): self
    {
        $this->startDateTime = $startDateTime;

        return $this;
    }

    public function getEndDateTime(): ?DateTime
    {
        return $this->endDateTime;
    }

    public function setEndDateTime(?DateTime $endDateTime): self
    {
        $this->endDateTime = $endDateTime;

        return $this;
    }
}
