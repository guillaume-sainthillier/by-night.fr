<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\ParserHistoryRepository;
use App\Utils\UnitOfWorkOptimizer;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParserHistoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ParserHistory
{
    use EntityIdentityTrait;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startDate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $endDate;

    #[ORM\Column(type: Types::STRING, length: 127)]
    private ?string $fromData = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $newEvents = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $updatedEvents = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $explorations = 0;

    public function __construct()
    {
        $this->startDate = new DateTimeImmutable();
        $this->endDate = new DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function updateEndDate(): void
    {
        $this->setEndDate(new DateTimeImmutable());
    }

    /**
     * Get duration in seconds.
     */
    public function getDuration(): int
    {
        return $this->endDate->getTimestamp() - $this->startDate->getTimestamp();
    }

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeImmutable $startDate): self
    {
        $this->startDate = UnitOfWorkOptimizer::getDateTimeValue($this->startDate, $startDate);

        return $this;
    }

    public function getFromData(): ?string
    {
        return $this->fromData;
    }

    public function setFromData(string $fromData): self
    {
        $this->fromData = $fromData;

        return $this;
    }

    public function getEndDate(): ?DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(DateTimeImmutable $endDate): self
    {
        $this->endDate = UnitOfWorkOptimizer::getDateTimeValue($this->endDate, $endDate);

        return $this;
    }

    public function getNewEvents(): ?int
    {
        return $this->newEvents;
    }

    public function setNewEvents(int $newEvents): self
    {
        $this->newEvents = $newEvents;

        return $this;
    }

    public function getUpdatedEvents(): ?int
    {
        return $this->updatedEvents;
    }

    public function setUpdatedEvents(int $updatedEvents): self
    {
        $this->updatedEvents = $updatedEvents;

        return $this;
    }

    public function getExplorations(): ?int
    {
        return $this->explorations;
    }

    public function setExplorations(int $explorations): self
    {
        $this->explorations = $explorations;

        return $this;
    }
}
