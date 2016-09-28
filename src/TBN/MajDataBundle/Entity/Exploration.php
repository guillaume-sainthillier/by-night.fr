<?php

namespace TBN\MajDataBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Exploration
 *
 * @ORM\Table(name="Exploration", indexes={
 *   @ORM\Index(name="exploration_facebook_id_site_idx", columns={"facebook_id", "site_id"})
 * })
 * @ORM\Entity
 */
class Exploration
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
     * @var string
     *
     * @ORM\Column(name="facebook_id", type="string", length=255)
     */
    private $facebookId;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="last_updated", type="datetime", nullable=true)
     */
    private $lastUpdated;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="black_listed", type="boolean", nullable=true)
     */
    private $blackListed;

    /**
     * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site", cascade={"persist", "merge"})
     * @ORM\JoinColumn(nullable=false)
     */
    protected $site;

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
     * Set facebookId
     *
     * @param string $facebookId
     *
     * @return Exploration
     */
    public function setFacebookId($facebookId)
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    /**
     * Get facebookId
     *
     * @return string
     */
    public function getFacebookId()
    {
        return $this->facebookId;
    }

    /**
     * Set lastUpdated
     *
     * @param \DateTime $lastUpdated
     *
     * @return Exploration
     */
    public function setLastUpdated($lastUpdated)
    {
        $this->lastUpdated = $lastUpdated;

        return $this;
    }

    /**
     * Get lastUpdated
     *
     * @return \DateTime
     */
    public function getLastUpdated()
    {
        return $this->lastUpdated;
    }

    /**
     * Set blackListed
     *
     * @param boolean $blackListed
     *
     * @return Exploration
     */
    public function setBlackListed($blackListed)
    {
        $this->blackListed = $blackListed;

        return $this;
    }

    /**
     * Get blackListed
     *
     * @return boolean
     */
    public function getBlackListed()
    {
        return $this->blackListed;
    }

    /**
     * Set reason
     *
     * @param string $reason
     *
     * @return Exploration
     */
    public function setReason($reason)
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Get reason
     *
     * @return string
     */
    public function getReason()
    {
        return $this->reason;
    }

    /**
     * Set site
     *
     * @param \TBN\MainBundle\Entity\Site $site
     *
     * @return Exploration
     */
    public function setSite(\TBN\MainBundle\Entity\Site $site)
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
