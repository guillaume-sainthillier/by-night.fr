<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use Doctrine\Common\Collections\ArrayCollection;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * User.
 *
 * @ORM\Table(name="User", indexes={@ORM\Index(name="user_nom_idx", columns={"nom"})})
 * @ORM\Entity(repositoryClass="AppBundle\Repository\UserRepository")
 * @ExclusionPolicy("all")
 * @Vich\Uploadable
 * @ORM\HasLifecycleCallbacks()
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"list_even", "list_user"})
     * @Expose
     */
    protected $id;

    /**
     * @Gedmo\Slug(fields={"username"})
     * @ORM\Column(length=128, unique=true)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="firstname", type="string", length=255, nullable=true)
     * @Serializer\Groups({"list_user"})
     * @Expose
     */
    protected $firstname;

    /**
     * @var string
     *
     * @ORM\Column(name="lastname", type="string", length=255, nullable=true)
     * @Serializer\Groups({"list_user"})
     * @Expose
     */
    protected $lastname;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=127, nullable=true)
     */
    protected $description;

    /**
     * @ORM\OneToOne(targetEntity="AppBundle\Entity\UserInfo", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    protected $info;

    /**
     * @ORM\OneToMany(targetEntity="AppBundle\Entity\Calendrier", mappedBy="user")
     */
    protected $calendriers;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Site")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $site;

    /**
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\City")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $city;

    /**
     * @ORM\Column(name="from_login", type="boolean", nullable=true)
     */
    protected $from_login;

    /**
     * @ORM\Column(name="show_socials", type="boolean", nullable=true)
     */
    protected $show_socials;

    /**
     * @ORM\Column(name="date_creation", type="datetime", nullable=true)
     */
    protected $date_creation;

    /**
     * @ORM\Column(name="website", type="string", length=255, nullable=true)
     */
    protected $website;

    /**
     * @Vich\UploadableField(mapping="user_image", fileNameProperty="path")
     * @Assert\Valid()
     * @Assert\File(maxSize = "6M")
     * @Assert\Image()
     *
     * @var File
     */
    private $imageFile;

    /**
     * @Vich\UploadableField(mapping="user_system_image", fileNameProperty="systemPath")
     * @Assert\Valid()
     * @Assert\File(maxSize = "6M")
     * @Assert\Image()
     *
     * @var File
     */
    private $imageSystemFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $path;

    /**
     * @ORM\Column(type="string", name="system_path", length=255, nullable=true)
     *
     * @var string
     */
    private $systemPath;

    /**
     * @ORM\Column(type="datetime")
     *
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->updatedAt = new \DateTime();
        $this->setFromLogin(false);
        $this->setShowSocials(true);
        $this->date_creation = new \DateTime();
        $this->calendriers   = new ArrayCollection();
        $this->info          = new UserInfo();
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
    public function setImageSystemFile(File $image = null)
    {
        $this->imageSystemFile = $image;

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
    public function getImageSystemFile()
    {
        return $this->imageSystemFile;
    }

    public function getUsername()
    {
        return \ucfirst(parent::getUsername());
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
     * Set firstname.
     *
     * @param string $firstname
     *
     * @return User
     */
    public function setFirstname($firstname)
    {
        $this->firstname = $firstname;

        return $this;
    }

    /**
     * Get firstname.
     *
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * Set lastname.
     *
     * @param string $lastname
     *
     * @return User
     */
    public function setLastname($lastname)
    {
        $this->lastname = $lastname;

        return $this;
    }

    /**
     * Get lastname.
     *
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return User
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set from_login.
     *
     * @param bool $fromLogin
     *
     * @return User
     */
    public function setFromLogin($fromLogin)
    {
        $this->from_login = $fromLogin;

        return $this;
    }

    /**
     * Get from_login.
     *
     * @return bool
     */
    public function getFromLogin()
    {
        return $this->from_login;
    }

    /**
     * Set date_creation.
     *
     * @param \DateTime $dateCreation
     *
     * @return User
     */
    public function setDateCreation($dateCreation)
    {
        $this->date_creation = $dateCreation;

        return $this;
    }

    /**
     * Get date_creation.
     *
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->date_creation;
    }

    /**
     * Set info.
     *
     * @param UserInfo $info
     *
     * @return User
     */
    public function setInfo(UserInfo $info = null)
    {
        $this->info = $info;

        return $this;
    }

    /**
     * Get info.
     *
     * @return \AppBundle\Entity\UserInfo
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * Add calendriers.
     *
     * @param Calendrier $calendriers
     *
     * @return User
     */
    public function addCalendrier(Calendrier $calendriers)
    {
        $this->calendriers[] = $calendriers;

        return $this;
    }

    /**
     * Remove calendriers.
     *
     * @param Calendrier $calendriers
     */
    public function removeCalendrier(Calendrier $calendriers)
    {
        $this->calendriers->removeElement($calendriers);
    }

    /**
     * Get calendriers.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCalendriers()
    {
        return $this->calendriers;
    }

    /**
     * Set site.
     *
     * @param Site $site
     *
     * @return User
     */
    public function setSite(Site $site)
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Get site.
     *
     * @return \AppBundle\Entity\Site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Set showSocials.
     *
     * @param bool $showSocials
     *
     * @return User
     */
    public function setShowSocials($showSocials)
    {
        $this->show_socials = $showSocials;

        return $this;
    }

    /**
     * Get showSocials.
     *
     * @return bool
     */
    public function getShowSocials()
    {
        return $this->show_socials;
    }

    /**
     * Set website.
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
     * Get website.
     *
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    public function __toString()
    {
        return \sprintf('#%s (%s)', $this->id ?: '?', $this->getUsername());
    }

    /**
     * Set path.
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
     * Get path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set updatedAt.
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
     * Get updatedAt.
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set systemPath.
     *
     * @param string $systemPath
     *
     * @return User
     */
    public function setSystemPath($systemPath)
    {
        $this->systemPath = $systemPath;

        return $this;
    }

    /**
     * Get systemPath.
     *
     * @return string
     */
    public function getSystemPath()
    {
        return $this->systemPath;
    }

    /**
     * Set slug.
     *
     * @param string $slug
     *
     * @return User
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug.
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set city.
     *
     * @param \AppBundle\Entity\City $city
     *
     * @return User
     */
    public function setCity(\AppBundle\Entity\City $city = null)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city.
     *
     * @return \AppBundle\Entity\City
     */
    public function getCity()
    {
        return $this->city;
    }
}
