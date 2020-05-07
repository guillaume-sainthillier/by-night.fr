<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use DateTimeImmutable;
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
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
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
    private ?string $slug = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"list_user"})
     * @Expose
     */
    private ?string $firstname = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Serializer\Groups({"list_user"})
     * @Expose
     */
    private ?string $lastname = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\UserInfo", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true)
     */
    private ?UserInfo $info = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Calendrier", mappedBy="user")
     */
    protected Collection $calendriers;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\City")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?City $city = null;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected bool $fromLogin;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private ?bool $showSocials = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $website = null;

    /**
     * @Vich\UploadableField(mapping="user_image", fileNameProperty="image.name", size="image.size", mimeType="image.mimeType", originalName="image.originalName", dimensions="image.dimensions")
     * @Assert\Valid
     * @Assert\File(maxSize="6M")
     * @Assert\Image
     */
    private ?File $imageFile = null;

    /**
     * @ORM\Embedded(class="Vich\UploaderBundle\Entity\File")
     */
    private EmbeddedFile $image;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $imageHash = null;

    /**
     * @Vich\UploadableField(mapping="user_image", fileNameProperty="imageSystem.name", size="imageSystem.size", mimeType="imageSystem.mimeType", originalName="imageSystem.originalName", dimensions="imageSystem.dimensions")
     * @Assert\Valid
     * @Assert\Image(maxSize="6M")
     */
    private ?File $imageSystemFile = null;

    /**
     * @ORM\Embedded(class="Vich\UploaderBundle\Entity\File")
     */
    private EmbeddedFile $imageSystem;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $imageSystemHash = null;

    public function __construct()
    {
        parent::__construct();

        $this->fromLogin = false;
        $this->showSocials = true;
        $this->calendriers = new ArrayCollection();
        $this->info = new UserInfo();
        $this->image = new EmbeddedFile();
        $this->imageSystem = new EmbeddedFile();
    }

    /**
     * @return File
     */
    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     */
    public function setImageFile(File $image = null): self
    {
        $this->imageFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    /**
     * @return File
     */
    public function getImageSystemFile(): ?File
    {
        return $this->imageSystemFile;
    }

    /**
     * If manually uploading a file (i.e. not using Symfony Form) ensure an instance
     * of 'UploadedFile' is injected into this setter to trigger the update. If this
     * bundle's configuration parameter 'inject_on_load' is set to 'true' this setter
     * must be able to accept an instance of 'File' as the bundle will inject one here
     * during Doctrine hydration.
     */
    public function setImageSystemFile(File $image = null): self
    {
        $this->imageSystemFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getUsername()
    {
        return \ucfirst(parent::getUsername());
    }

    public function __toString()
    {
        return \sprintf('%s (#%s)', $this->username, $this->id);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(?string $lastname): self
    {
        $this->lastname = $lastname;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getFromLogin(): ?bool
    {
        return $this->fromLogin;
    }

    public function setFromLogin(?bool $fromLogin): self
    {
        $this->fromLogin = $fromLogin;

        return $this;
    }

    public function getShowSocials(): ?bool
    {
        return $this->showSocials;
    }

    public function setShowSocials(?bool $showSocials): self
    {
        $this->showSocials = $showSocials;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getInfo(): ?UserInfo
    {
        return $this->info;
    }

    public function setInfo(?UserInfo $info): self
    {
        $this->info = $info;

        return $this;
    }

    /**
     * @return Collection|Calendrier[]
     */
    public function getCalendriers(): Collection
    {
        return $this->calendriers;
    }

    public function addCalendrier(Calendrier $calendrier): self
    {
        if (!$this->calendriers->contains($calendrier)) {
            $this->calendriers[] = $calendrier;
            $calendrier->setUser($this);
        }

        return $this;
    }

    public function removeCalendrier(Calendrier $calendrier): self
    {
        if ($this->calendriers->contains($calendrier)) {
            $this->calendriers->removeElement($calendrier);
            // set the owning side to null (unless already changed)
            if ($calendrier->getUser() === $this) {
                $calendrier->setUser(null);
            }
        }

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getImage(): \Vich\UploaderBundle\Entity\File
    {
        return $this->image;
    }

    public function setImage(\Vich\UploaderBundle\Entity\File $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getImageSystem(): \Vich\UploaderBundle\Entity\File
    {
        return $this->imageSystem;
    }

    public function setImageSystem(\Vich\UploaderBundle\Entity\File $imageSystem): self
    {
        $this->imageSystem = $imageSystem;

        return $this;
    }

    public function getImageHash(): ?string
    {
        return $this->imageHash;
    }

    public function setImageHash(?string $imageHash): self
    {
        $this->imageHash = $imageHash;

        return $this;
    }

    public function getImageSystemHash(): ?string
    {
        return $this->imageSystemHash;
    }

    public function setImageSystemHash(?string $imageSystemHash): self
    {
        $this->imageSystemHash = $imageSystemHash;

        return $this;
    }
}
