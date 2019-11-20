<?php

namespace App\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * HistoriqueMaj.
 *
 * @ORM\Table(name="HistoriqueMaj")
 * @ORM\Entity(repositoryClass="App\Repository\HistoriqueMajRepository", readOnly=true)
 * @ORM\HasLifecycleCallbacks
 */
class HistoriqueMaj
{
    use EntityIdentityTrait;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_debut", type="datetime")
     */
    private $dateDebut;

    /**
     * @var string
     *
     * @ORM\Column(name="from_data", type="string", length=127)
     */
    private $fromData;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_fin", type="datetime")
     */
    private $dateFin;

    /**
     * @var int
     *
     * @ORM\Column(name="nouvelles_soirees", type="integer")
     */
    private $nouvellesSoirees;

    /**
     * @var int
     *
     * @ORM\Column(name="update_soirees", type="integer")
     */
    private $updateSoirees;

    /**
     * @var int
     *
     * @ORM\Column(name="explorations", type="integer")
     */
    private $explorations;

    public function __construct()
    {
        $this->dateDebut = new DateTime();
    }

    /**
     * @ORM\PrePersist
     */
    public function majDateFin()
    {
        $this->dateFin = new DateTime();
    }

    /**
     * Get duree.
     *
     * @return int
     */
    public function getDuree()
    {
        return $this->dateFin->getTimestamp() - $this->dateDebut->getTimestamp();
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): self
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

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): self
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
