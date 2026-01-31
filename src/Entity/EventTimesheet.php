<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Utils\UnitOfWorkOptimizer;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Index(name: 'event_timesheet_event_idx', columns: ['event_id'])]
#[ORM\Index(name: 'event_timesheet_start_idx', columns: ['start_at'])]
#[ORM\Index(name: 'event_timesheet_end_idx', columns: ['end_at'])]
class EventTimesheet implements Stringable
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'timesheets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['elasticsearch:event:details'])]
    private ?DateTime $startAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['elasticsearch:event:details'])]
    private ?DateTime $endAt = null;

    #[ORM\Column(type: Types::STRING, length: 256, nullable: true)]
    private ?string $hours = null;

    public function __toString(): string
    {
        return \sprintf('%s - %s',
            $this->startAt?->format('d/m/Y H:i') ?? 'N/A',
            $this->endAt?->format('d/m/Y H:i') ?? 'N/A'
        );
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

    public function getStartAt(): ?DateTime
    {
        return $this->startAt;
    }

    public function setStartAt(?DateTime $startAt): self
    {
        $this->startAt = UnitOfWorkOptimizer::getDateTimeValue($this->startAt, $startAt);

        return $this;
    }

    public function getEndAt(): ?DateTime
    {
        return $this->endAt;
    }

    public function setEndAt(?DateTime $endAt): self
    {
        $this->endAt = UnitOfWorkOptimizer::getDateTimeValue($this->endAt, $endAt);

        return $this;
    }

    public function getHours(): ?string
    {
        return $this->hours;
    }

    public function setHours(?string $hours): self
    {
        $this->hours = $hours;

        return $this;
    }
}
