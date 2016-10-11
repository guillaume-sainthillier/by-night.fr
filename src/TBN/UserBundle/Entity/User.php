<?php

namespace TBN\UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use Doctrine\Common\Collections\ArrayCollection;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\HttpFoundation\File\File;

/**
 * User
 *
 * @ORM\Table(name="User", indexes={@ORM\Index(name="user_nom_idx", columns={"nom"})})
 * @ORM\Entity(repositoryClass="TBN\UserBundle\Entity\UserRepository")
 * @ExclusionPolicy("all")
 * @Vich\Uploadable
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
     * @ORM\OneToOne(targetEntity="TBN\UserBundle\Entity\UserInfo", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    protected $info;

    /**
     * @ORM\OneToMany(targetEntity="TBN\AgendaBundle\Entity\Calendrier", mappedBy="user")
     */
    protected $calendriers;

    /**
     * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site")
     * @ORM\JoinColumn(nullable=false)
     * @Expose
     */
    protected $site;

    /**
     * @ORM\Column(name="from_login", type="boolean", nullable=true)
     */
    protected $from_login;

    /**
     * @ORM\Column(name="show_socials", type="boolean", nullable=true)
     * @Expose
     */
    protected $show_socials;

    /**
     * @ORM\Column(name="date_creation", type="datetime", nullable=true)
     * @Expose
     */
    protected $date_creation;

    /**
     * @ORM\Column(name="website", type="string", length=255, nullable=true)
     * @Expose
     */
    protected $website;

    /**
     *
     * @Vich\UploadableField(mapping="user_image", fileNameProperty="path")
     *
     * @var File
     */
    private $imageFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Expose
     *
     * @var string
     */
    private $path;

    /**
     * @ORM\Column(type="datetime")
     * @Expose
     *
     * @var \DateTime
     */
    private $updatedAt;


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->updatedAt = new \DateTime();
        $this->setFromLogin(false);
        $this->setShowSocials(true);
        $this->date_creation = new \DateTime();
        $this->evenements = new ArrayCollection();
        $this->calendriers = new ArrayCollection();
        $this->info = new UserInfo();
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the  update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     *
     * @param File|\Symfony\Component\HttpFoundation\File\UploadedFile $image
     *
     * @return User
     */
    public function setImageFile(File $image = null)
    {
        $this->imageFile = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTime('now');
        }

        return $this;
    }

    /**
     * @return File
     */
    public function getImageFile()
    {
        return $this->imageFile;
    }

    public function getExpiresAt()
    {
        return $this->expiresAt;
    }

    public function getCredentialsExpireAt()
    {
        return $this->credentialsExpireAt;
    }

        public function getUsername()
    {
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

    /**
     * Set showSocials
     *
     * @param boolean $showSocials
     *
     * @return User
     */
    public function setShowSocials($showSocials)
    {
        $this->show_socials = $showSocials;

        return $this;
    }

    /**
     * Get showSocials
     *
     * @return boolean
     */
    public function getShowSocials()
    {
        return $this->show_socials;
    }

    /**
     * Set website
     *
     * @param string $website
     *
     * @return User
     */
    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }

    /**
     * Get website
     *
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    public function __toString()
    {
        return sprintf("#%s (%s)", $this->id ?: '?', $this->getUsername());
    }

    /**
     * Set path
     *
     * @param string $path
     *
     * @return User
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return User
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }
}
