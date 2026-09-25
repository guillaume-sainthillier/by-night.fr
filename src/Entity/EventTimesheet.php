<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\EventTimesheetRepository;
use App\Utils\UnitOfWorkOptimizer;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ORM\Entity(repositoryClass: EventTimesheetRepository::class)]
#[ORM\Index(name: 'event_timesheet_event_idx', columns: ['event_id'])]
#[ORM\Index(name: 'event_timesheet_start_idx', columns: ['start_at'])]
#[ORM\Index(name: 'event_timesheet_end_idx', columns: ['end_at'])]
#[ORM\Index(name: 'event_timesheet_source_event_idx', columns: ['source_event_id'])]
class EventTimesheet implements Stringable
{
    use EntityIdentityTrait;
    use EntityTimestampableTrait;

    #[ORM\ManyToOne(targetEntity: Event::class, inversedBy: 'timesheets')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Event $event = null;

    /**
     * Set on the rows a canonical event inherits from a duplicate sibling of its family
     * (see EventFamilyResolver): the row mirrors one of that sibling's own timesheets,
     * is rebuilt whenever the sibling changes and goes away with it (ON DELETE CASCADE).
     * NULL on an event's own timesheets, the ones its import keeps in sync.
     */
    #[ORM\ManyToOne(targetEntity: Event::class)]
    #[ORM\JoinColumn(name: 'source_event_id', nullable: true, onDelete: 'CASCADE')]
    private ?Event $sourceEvent = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['elasticsearch:event:details'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTimeImmutable $startAt = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['elasticsearch:event:details'])]
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?DateTimeImmutable $endAt = null;

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

    public function getSourceEvent(): ?Event
    {
        return $this->sourceEvent;
    }

    public function setSourceEvent(?Event $sourceEvent): self
    {
        $this->sourceEvent = $sourceEvent;

        return $this;
    }

    /**
     * Whether this row was lent by a duplicate sibling rather than imported for this event.
     */
    public function isInherited(): bool
    {
        return null !== $this->sourceEvent;
    }

    public function getStartAt(): ?DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(?DateTimeImmutable $startAt): self
    {
        $this->startAt = UnitOfWorkOptimizer::getDateTimeValue($this->startAt, $startAt);

        return $this;
    }

    public function getEndAt(): ?DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(?DateTimeImmutable $endAt): self
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
