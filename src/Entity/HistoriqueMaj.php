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
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

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
     * @ORM\PrePersist()
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

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set dateDebut.
     *
     * @param DateTime $dateDebut
     *
     * @return HistoriqueMaj
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    /**
     * Get dateDebut.
     *
     * @return DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * Set fromData.
     *
     * @param string $fromData
     *
     * @return HistoriqueMaj
     */
    public function setFromData($fromData)
    {
        $this->fromData = $fromData;

        return $this;
    }

    /**
     * Get fromData.
     *
     * @return string
     */
    public function getFromData()
    {
        return $this->fromData;
    }

    /**
     * Set dateFin.
     *
     * @param DateTime $dateFin
     *
     * @return HistoriqueMaj
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Get dateFin.
     *
     * @return DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * Set nouvellesSoirees.
     *
     * @param int $nouvellesSoirees
     *
     * @return HistoriqueMaj
     */
    public function setNouvellesSoirees($nouvellesSoirees)
    {
        $this->nouvellesSoirees = $nouvellesSoirees;

        return $this;
    }

    /**
     * Get nouvellesSoirees.
     *
     * @return int
     */
    public function getNouvellesSoirees()
    {
        return $this->nouvellesSoirees;
    }

    /**
     * Set updateSoirees.
     *
     * @param int $updateSoirees
     *
     * @return HistoriqueMaj
     */
    public function setUpdateSoirees($updateSoirees)
    {
        $this->updateSoirees = $updateSoirees;

        return $this;
    }

    /**
     * Get updateSoirees.
     *
     * @return int
     */
    public function getUpdateSoirees()
    {
        return $this->updateSoirees;
    }

    /**
     * Set explorations.
     *
     * @param int $explorations
     *
     * @return HistoriqueMaj
     */
    public function setExplorations($explorations)
    {
        $this->explorations = $explorations;

        return $this;
    }

    /**
     * Get explorations.
     *
     * @return int
     */
    public function getExplorations()
    {
        return $this->explorations;
    }
}
