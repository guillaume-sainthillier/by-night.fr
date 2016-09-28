<?php

namespace TBN\MajDataBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * HistoriqueMaj
 *
 * @ORM\Table(name="HistoriqueMaj")
 * @ORM\Entity(repositoryClass="TBN\MajDataBundle\Entity\HistoriqueMajRepository")
 * @ORM\HasLifecycleCallbacks
 */
class HistoriqueMaj
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site", cascade={"persist", "merge"}, fetch="EAGER")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $site;

    /**
     * @var \DateTime
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
     * @var \DateTime
     *
     * @ORM\Column(name="date_fin", type="datetime")
     */
    private $dateFin;

    /**
     * @var integer
     *
     * @ORM\Column(name="nouvelles_soirees", type="integer")
     */
    private $nouvellesSoirees;

    /**
     * @var integer
     *
     * @ORM\Column(name="update_soirees", type="integer")
     */
    private $updateSoirees;

    /**
     * @var integer
     *
     * @ORM\Column(name="explorations", type="integer")
     */
    private $explorations;


    public function __construct()
    {
        $this->dateDebut = new \DateTime;
    }

    /**
     * @ORM\PrePersist()
     */
    public function majDateFin()
    {
        $this->dateFin = new \DateTime;
    }

    /**
     * Get duree
     *
     * @return integer
     */
    public function getDuree()
    {
        return $this->dateFin->getTimestamp() - $this->dateDebut->getTimestamp();
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set dateDebut
     *
     * @param \DateTime $dateDebut
     *
     * @return HistoriqueMaj
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    /**
     * Get dateDebut
     *
     * @return \DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * Set fromData
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
     * Get fromData
     *
     * @return string
     */
    public function getFromData()
    {
        return $this->fromData;
    }

    /**
     * Set dateFin
     *
     * @param \DateTime $dateFin
     *
     * @return HistoriqueMaj
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Get dateFin
     *
     * @return \DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * Set nouvellesSoirees
     *
     * @param integer $nouvellesSoirees
     *
     * @return HistoriqueMaj
     */
    public function setNouvellesSoirees($nouvellesSoirees)
    {
        $this->nouvellesSoirees = $nouvellesSoirees;

        return $this;
    }

    /**
     * Get nouvellesSoirees
     *
     * @return integer
     */
    public function getNouvellesSoirees()
    {
        return $this->nouvellesSoirees;
    }

    /**
     * Set updateSoirees
     *
     * @param integer $updateSoirees
     *
     * @return HistoriqueMaj
     */
    public function setUpdateSoirees($updateSoirees)
    {
        $this->updateSoirees = $updateSoirees;

        return $this;
    }

    /**
     * Get updateSoirees
     *
     * @return integer
     */
    public function getUpdateSoirees()
    {
        return $this->updateSoirees;
    }

    /**
     * Set explorations
     *
     * @param integer $explorations
     *
     * @return HistoriqueMaj
     */
    public function setExplorations($explorations)
    {
        $this->explorations = $explorations;

        return $this;
    }

    /**
     * Get explorations
     *
     * @return integer
     */
    public function getExplorations()
    {
        return $this->explorations;
    }

    /**
     * Set site
     *
     * @param \TBN\MainBundle\Entity\Site $site
     *
     * @return HistoriqueMaj
     */
    public function setSite(\TBN\MainBundle\Entity\Site $site = null)
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Get site
     *
     * @return \TBN\MainBundle\Entity\Site
     */
    public function getSite()
    {
        return $this->site;
    }
}
