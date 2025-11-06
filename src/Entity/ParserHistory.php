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
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParserHistoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ParserHistory
{
    use EntityIdentityTrait;
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $startDate;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private DateTime $endDate;

    #[ORM\Column(type: Types::STRING, length: 127)]
    private ?string $fromData = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $nouvellesSoirees = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $updateSoirees = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $explorations = 0;

    public function __construct()
    {
        $this->startDate = new DateTime();
        $this->endDate = new DateTime();
    }

    #[ORM\PrePersist]
    public function majEndDate(): void
    {
        $this->setEndDate(new DateTime());
    }

    /**
     * Get duree.
     */
    public function getDuree(): int
    {
        return $this->endDate->getTimestamp() - $this->startDate->getTimestamp();
    }

    public function getStartDate(): ?DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(DateTime $startDate): self
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

    public function getEndDate(): ?DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(DateTime $endDate): self
    {
        $this->endDate = UnitOfWorkOptimizer::getDateTimeValue($this->endDate, $endDate);

        return $this;
    }

    public function getNouvellesSoirees(): ?int
    {
        return $this->nouvellesSoirees;
    }

    public function setNouvellesSoirees(int $nouvellesSoirees): self
    {
        $this->nouvellesSoirees = $nouvellesSoirees;

        return $this;
    }

    public function getUpdateSoirees(): ?int
    {
        return $this->updateSoirees;
    }

    public function setUpdateSoirees(int $updateSoirees): self
    {
        $this->updateSoirees = $updateSoirees;

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
