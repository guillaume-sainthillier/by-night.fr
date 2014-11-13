<?php

namespace TBN\MajDataBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * BlackList
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="TBN\MajDataBundle\Entity\BlackListRepository")
 */
class BlackList
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="facebook_id", type="string", length=255)
     */
    private $facebookId;

    /**
     * @var string
     *
     * @ORM\Column(name="reason", type="string", length=255)
     */
    private $reason;


    /**
    * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site")
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
     * @return BlackList
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
     * Set reason
     *
     * @param string $reason
     * @return BlackList
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
     * @return BlackList
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
