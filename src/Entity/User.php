<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use Serializable;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[UniqueEntity(fields: ['email'], message: 'Un utilisateur existe déjà pour cet email')]
#[UniqueEntity(fields: ['username'], message: 'Un utilisateur existe déjà pour ce nom')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ExclusionPolicy('all')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, Serializable, Stringable
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Serializer\Groups(['list_event', 'list_user'])]
    #[Expose]
    private ?int $id = null;

    #[Assert\Length(max: 180)]
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $salt = null;

    #[Assert\Length(max: 180)]
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $lastLogin;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $passwordRequestedAt = null;

    #[ORM\Column(type: 'array')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    #[ORM\Column(length: 128, unique: true)]
    #[Gedmo\Slug(fields: ['username'])]
    private ?string $slug = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['list_user'])]
    #[Expose]
    private ?string $firstname = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Serializer\Groups(['list_user'])]
    #[Expose]
    private ?string $lastname = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\OneToOne(targetEntity: UserOAuth::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?UserOAuth $oAuth;

    /**
     * @var Collection<int, UserEvent>
     */
    #[ORM\OneToMany(targetEntity: UserEvent::class, mappedBy: 'user')]
    private Collection $userEvents;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?City $city = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private bool $fromLogin = false;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private bool $showSocials = true;

    #[Assert\Url]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $website = null;

    #[Vich\UploadableField(mapping: 'user_image', fileNameProperty: 'image.name', size: 'image.size', mimeType: 'image.mimeType', originalName: 'image.originalName', dimensions: 'image.dimensions')]
    #[Assert\Valid]
    #[Assert\File(maxSize: '6M')]
    #[Assert\Image]
    private ?File $imageFile = null;

    #[ORM\Embedded(class: \Vich\UploaderBundle\Entity\File::class)]
    private EmbeddedFile $image;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageHash = null;

    #[Vich\UploadableField(mapping: 'user_image', fileNameProperty: 'imageSystem.name', size: 'imageSystem.size', mimeType: 'imageSystem.mimeType', originalName: 'imageSystem.originalName', dimensions: 'imageSystem.dimensions')]
    #[Assert\Valid]
    #[Assert\Image(maxSize: '6M')]
    private ?File $imageSystemFile = null;

    #[ORM\Embedded(class: \Vich\UploaderBundle\Entity\File::class)]
    private EmbeddedFile $imageSystem;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageSystemHash = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    public function __construct()
    {
        $this->lastLogin = new DateTime();
        $this->userEvents = new ArrayCollection();
        $this->oAuth = new UserOAuth();
        $this->image = new EmbeddedFile();
        $this->imageSystem = new EmbeddedFile();
    }

    public function addRole(string $role): self
    {
        $role = strtoupper($role);
        if ('ROLE_USER' === $role) {
            return $this;
        }

        if (!\in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole(string $role): self
    {
        if (false !== $key = array_search(strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }

        return $this;
    }

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
        return ucfirst($this->username);
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function __toString(): string
    {
        return sprintf('%s (#%s)', $this->username, $this->id);
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * @see UserInterface
     *
     * @return void
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return serialize([
            $this->password,
            $this->username,
            $this->enabled,
            $this->id,
            $this->email,
            $this->salt,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($data)
    {
        $data = unserialize($data);

        [$this->password, $this->username, $this->enabled, $this->id, $this->email, $this->salt] = $data;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
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

    public function setFromLogin(bool $fromLogin): self
    {
        $this->fromLogin = $fromLogin;

        return $this;
    }

    public function getShowSocials(): ?bool
    {
        return $this->showSocials;
    }

    public function setShowSocials(bool $showSocials): self
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

    public function getOAuth(): ?UserOAuth
    {
        return $this->oAuth;
    }

    public function setOAuth(?UserOAuth $oAuth): self
    {
        $this->oAuth = $oAuth;

        return $this;
    }

    /**
     * @return Collection<int, UserEvent>
     */
    public function getUserEvents(): Collection
    {
        return $this->userEvents;
    }

    public function addUserEvent(UserEvent $userEvent): self
    {
        if (!$this->userEvents->contains($userEvent)) {
            $this->userEvents[] = $userEvent;
            $userEvent->setUser($this);
        }

        return $this;
    }

    public function removeUserEvent(UserEvent $userEvent): self
    {
        if ($this->userEvents->contains($userEvent)) {
            $this->userEvents->removeElement($userEvent);
            // set the owning side to null (unless already changed)
            if ($userEvent->getUser() === $this) {
                $userEvent->setUser(null);
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

    public function getImage(): EmbeddedFile
    {
        return $this->image;
    }

    public function setImage(EmbeddedFile $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getImageSystem(): EmbeddedFile
    {
        return $this->imageSystem;
    }

    public function setImageSystem(EmbeddedFile $imageSystem): self
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?DateTimeInterface $lastLogin): self
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getPasswordRequestedAt(): ?DateTimeInterface
    {
        return $this->passwordRequestedAt;
    }

    public function setPasswordRequestedAt(?DateTimeInterface $passwordRequestedAt): self
    {
        $this->passwordRequestedAt = $passwordRequestedAt;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    public function getIsVerified(): ?bool
    {
        return $this->isVerified;
    }
}
