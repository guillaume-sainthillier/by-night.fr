<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\ParserHistoryRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParserHistoryRepository::class, readOnly: true)]
#[ORM\HasLifecycleCallbacks]
class ParserHistory
{
    use EntityIdentityTrait;
    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $dateDebut;

    #[ORM\Column(type: 'datetime')]
    private DateTimeInterface $dateFin;

    #[ORM\Column(type: 'string', length: 127)]
    private ?string $fromData = null;

    #[ORM\Column(type: 'integer')]
    private int $nouvellesSoirees = 0;

    #[ORM\Column(type: 'integer')]
    private int $updateSoirees = 0;

    #[ORM\Column(type: 'integer')]
    private int $explorations = 0;

    public function __construct()
    {
        $this->dateDebut = new DateTime();
        $this->dateFin = new DateTime();
    }

    #[ORM\PrePersist]
    public function majDateFin(): void
    {
        $this->dateFin = new DateTime();
    }

    /**
     * Get duree.
     */
    public function getDuree(): int
    {
        return $this->dateFin->getTimestamp() - $this->dateDebut->getTimestamp();
    }

    public function getDateDebut(): ?DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

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

    public function getDateFin(): ?DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;

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
