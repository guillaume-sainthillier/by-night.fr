<?php

namespace TBN\UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * User
 * 
 * @ORM\Table( name="tbn_user",
 *             indexes={@ORM\Index(
 *                  name="recherche_user_idx",
 *                  columns={"nom", "date_creation"}
 * )})
 * @ORM\Entity(repositoryClass="TBN\UserBundle\Entity\UserRepository")
 * @ExclusionPolicy("all")
 */
class User extends BaseUser
{
     /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
      * @Expose
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255, nullable=true)
     * @Expose
     */
    protected $nom;
    
    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=255, nullable=true)
     * @Expose
     */
    protected $firstname;
 
    /**
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=255, nullable=true)
     * @Expose
     */
    protected $lastname;
    
    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=127, nullable=true)
     * @Expose
     */
    protected $description;
    
    /**
    * @ORM\OneToMany(targetEntity="TBN\AgendaBundle\Entity\Agenda", mappedBy="user")
    * @ORM\OrderBy({"dateModification" = "DESC"})
    */
    protected $evenements;
 
    /**
     * @ORM\OneToOne(targetEntity="TBN\UserBundle\Entity\UserInfo", cascade={"persist", "remove"}, fetch="EAGER")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $info;
    
    /**
    * @ORM\OneToMany(targetEntity="TBN\AgendaBundle\Entity\Calendrier", mappedBy="agenda")
    */
    protected $calendriers;
    
    /**
    * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site")
    * @ORM\JoinColumn(nullable=false)
    */
    protected $site;
    
    /**
     * @ORM\Column(name="from_login", type="boolean", nullable=true)
     */
    protected $from_login;
    
    /**
     * @ORM\Column(name="date_creation", type="datetime", nullable=true)
     */
    protected $date_creation;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->setFromLogin(false);
        $this->date_creation = new \DateTime();
        $this->evenements = new \Doctrine\Common\Collections\ArrayCollection();
        $this->calendriers = new \Doctrine\Common\Collections\ArrayCollection();
        $this->info = new UserInfo();
    }
    
      
    public function getProfileDefault()
    {    
        $info = $this->getInfo();

        if($info !== null)
        {
            if($info->getFacebookProfilePicture() != null)
            {
                return $info->getFacebookProfilePicture();
            }elseif($info->getTwitterProfilePicture() != null)
            {
                return $info->getTwitterProfilePicture();
            }elseif($info->getGoogleProfilePicture() != null)
            {
                return $info->getGoogleProfilePicture();
            }
        }
        
        
        return "http://placehold.it/250&text=".$this->getUsername();
    }
    
    public function getUsername() {
        return ucfirst(parent::getUsername());
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
     * Set nom
     *
     * @param string $nom
     * @return User
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string 
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set firstname
     *
     * @param string $firstname
     * @return User
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get firstname
     *
     * @return string 
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set lastname
     *
     * @param string $lastname
     * @return User
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname
     *
     * @return string 
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return User
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set from_login
     *
     * @param boolean $fromLogin
     * @return User
     */
    public function setFromLogin($fromLogin)
    {
        $this->from_login = $fromLogin;

        return $this;
    }

    /**
     * Get from_login
     *
     * @return boolean 
     */
    public function getFromLogin()
    {
        return $this->from_login;
    }

    /**
     * Set date_creation
     *
     * @param \DateTime $dateCreation
     * @return User
     */
    public function setDateCreation($dateCreation)
    {
        $this->date_creation = $dateCreation;

        return $this;
    }

    /**
     * Get date_creation
     *
     * @return \DateTime 
     */
    public function getDateCreation()
    {
        return $this->date_creation;
    }

    /**
     * Add evenements
     *
     * @param \TBN\AgendaBundle\Entity\Agenda $evenements
     * @return User
     */
    public function addEvenement(\TBN\AgendaBundle\Entity\Agenda $evenements)
    {
        $this->evenements[] = $evenements;

        return $this;
    }

    /**
     * Remove evenements
     *
     * @param \TBN\AgendaBundle\Entity\Agenda $evenements
     */
    public function removeEvenement(\TBN\AgendaBundle\Entity\Agenda $evenements)
    {
        $this->evenements->removeElement($evenements);
    }

    /**
     * Get evenements
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getEvenements()
    {
        return $this->evenements;
    }

    /**
     * Set info
     *
     * @param \TBN\UserBundle\Entity\UserInfo $info
     * @return User
     */
    public function setInfo(\TBN\UserBundle\Entity\UserInfo $info = null)
    {
        $this->info = $info;

        return $this;
    }

    /**
     * Get info
     *
     * @return \TBN\UserBundle\Entity\UserInfo 
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * Add calendriers
     *
     * @param \TBN\AgendaBundle\Entity\Calendrier $calendriers
     * @return User
     */
    public function addCalendrier(\TBN\AgendaBundle\Entity\Calendrier $calendriers)
    {
        $this->calendriers[] = $calendriers;

        return $this;
    }

    /**
     * Remove calendriers
     *
     * @param \TBN\AgendaBundle\Entity\Calendrier $calendriers
     */
    public function removeCalendrier(\TBN\AgendaBundle\Entity\Calendrier $calendriers)
    {
        $this->calendriers->removeElement($calendriers);
    }

    /**
     * Get calendriers
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getCalendriers()
    {
        return $this->calendriers;
    }

    /**
     * Set site
     *
     * @param \TBN\MainBundle\Entity\Site $site
     * @return User
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
