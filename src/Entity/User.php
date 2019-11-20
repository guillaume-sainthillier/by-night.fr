<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use FOS\UserBundle\Model\User as BaseUser;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * User.
 *
 * @ORM\Table(name="User")
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ExclusionPolicy("all")
 * @Vich\Uploadable
 * @ORM\HasLifecycleCallbacks
 */
class User extends BaseUser
{
    use EntityTimestampableTrait;
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Serializer\Groups({"list_event", "list_user"})
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
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"list_user"})
     * @Expose
     */
    protected $firstname;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"list_user"})
     * @Expose
     */
    protected $lastname;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $description;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\UserInfo", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    protected $info;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Calendrier", mappedBy="user")
     */
    protected $calendriers;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\City")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $city;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $fromLogin;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $showSocials;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $website;

    /**
     * @Vich\UploadableField(mapping="user_image", fileNameProperty="path")
     * @Assert\Valid
     * @Assert\File(maxSize="6M")
     * @Assert\Image
     *
     * @var File
     */
    private $imageFile;

    /**
     * @Vich\UploadableField(mapping="user_image", fileNameProperty="systemPath")
     * @Assert\Valid
     * @Assert\File(maxSize="6M")
     * @Assert\Image
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
     * @ORM\Column(type="string", length=255, nullable=true)
     *
     * @var string
     */
    private $systemPath;

    public function __construct()
    {
        parent::__construct();

        $this->setFromLogin(false);
        $this->setShowSocials(true);
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
     * @param File|UploadedFile $image
     *
     * @return User
     */
    public function setImageFile(File $image = null)
    {
        $this->imageFile = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
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
     * @param File|UploadedFile $image
     *
     * @return User
     */
    public function setImageSystemFile(File $image = null)
    {
        $this->imageSystemFile = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
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
     * Set fromLogin.
     *
     * @param bool $fromLogin
     *
     * @return User
     */
    public function setFromLogin($fromLogin)
    {
        $this->fromLogin = $fromLogin;

        return $this;
    }

    /**
     * Get fromLogin.
     *
     * @return bool
     */
    public function getFromLogin()
    {
        return $this->fromLogin;
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
     * @return UserInfo
     */
    public function getInfo()
    {
        return $this->info;
    }

    /**
     * Add calendriers.
     *
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
     */
    public function removeCalendrier(Calendrier $calendriers)
    {
        $this->calendriers->removeElement($calendriers);
    }

    /**
     * Get calendriers.
     *
     * @return Collection
     */
    public function getCalendriers()
    {
        return $this->calendriers;
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
        $this->showSocials = $showSocials;

        return $this;
    }

    /**
     * Get showSocials.
     *
     * @return bool
     */
    public function getShowSocials()
    {
        return $this->showSocials;
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
        return \sprintf('%s (#%s)', $this->username, $this->id);
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
     * @param City $city
     *
     * @return User
     */
    public function setCity(City $city = null)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city.
     *
     * @return City
     */
    public function getCity()
    {
        return $this->city;
    }
}
