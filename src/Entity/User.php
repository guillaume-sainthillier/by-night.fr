<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Doctrine\EntityListener\UserEmailEntityListener;
use App\Repository\UserRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;
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
#[ORM\Table(name: '`user`')]
#[ORM\EntityListeners([UserEmailEntityListener::class])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, Serializable, Stringable, InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    use EntityTimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    #[Serializer\Groups(['elasticsearch:event:details', 'elasticsearch:user:details'])]
    private ?int $id = null;

    #[Assert\Length(max: 180)]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $salt = null;

    #[Assert\Length(max: 180)]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Serializer\Groups(['elasticsearch:user:details'])]
    private ?string $username = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastLogin;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $passwordRequestedAt = null;

    #[ORM\Column(type: Types::ARRAY)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING)]
    private ?string $password = null;

    #[ORM\Column(length: 128, unique: true)]
    #[Gedmo\Slug(fields: ['username'])]
    private ?string $slug = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Groups(['elasticsearch:user:details'])]
    private ?string $firstname = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Serializer\Groups(['elasticsearch:user:details'])]
    private ?string $lastname = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\OneToOne(targetEntity: UserOAuth::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn]
    private ?UserOAuth $oAuth;

    /**
     * @var Collection<int, UserEvent>
     */
    #[ORM\OneToMany(targetEntity: UserEvent::class, mappedBy: 'user')]
    private Collection $userEvents;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn]
    private ?City $city = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private bool $fromLogin = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private bool $showSocials = true;

    #[Assert\Url]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $website = null;

    #[Vich\UploadableField(mapping: 'user_image', fileNameProperty: 'image.name', size: 'image.size', mimeType: 'image.mimeType', originalName: 'image.originalName', dimensions: 'image.dimensions')]
    #[Assert\Valid]
    #[Assert\File(maxSize: '6M')]
    #[Assert\Image]
    private ?File $imageFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $image;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $imageHash = null;

    #[Vich\UploadableField(mapping: 'user_image', fileNameProperty: 'imageSystem.name', size: 'imageSystem.size', mimeType: 'imageSystem.mimeType', originalName: 'imageSystem.originalName', dimensions: 'imageSystem.dimensions')]
    #[Assert\Valid]
    #[Assert\Image(maxSize: '6M')]
    private ?File $imageSystemFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $imageSystem;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $imageSystemHash = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $verified = false;

    public function __construct()
    {
        $this->lastLogin = new DateTime();
        $this->userEvents = new ArrayCollection();
        $this->oAuth = new UserOAuth();
        $this->image = new EmbeddedFile();
        $this->imageSystem = new EmbeddedFile();
    }

    public function getInternalId(): ?string
    {
        if (null === $this->getId()) {
            return null;
        }

        return \sprintf(
            '%s-id-%s',
            $this->getKeyPrefix(),
            $this->getId()
        );
    }

    public function getKeyPrefix(): string
    {
        return 'user';
    }

    public function hasImage(): bool
    {
        return (null !== $this->image->getName() && '' !== $this->image->getName())
            || (null !== $this->imageSystem->getName() && '' !== $this->imageSystem->getName());
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
    public function setImageFile(?File $image = null): self
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
    public function setImageSystemFile(?File $image = null): self
    {
        $this->imageSystemFile = $image;

        if (null !== $image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getUsername(): string
    {
        return ucfirst((string) $this->username);
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function __toString(): string
    {
        return \sprintf('%s (#%s)', $this->username, $this->id);
    }

    /**
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        return $this->salt;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function __serialize(): array
    {
        return [
            $this->password,
            $this->username,
            $this->enabled,
            $this->id,
            $this->email,
            $this->salt,
        ];
    }

    public function __unserialize(array $data): void
    {
        [$this->password, $this->username, $this->enabled, $this->id, $this->email, $this->salt] = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($data): void
    {
        $data = unserialize($data);

        $this->__unserialize($data);
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

    public function isFromLogin(): ?bool
    {
        return $this->fromLogin;
    }

    public function setFromLogin(bool $fromLogin): self
    {
        $this->fromLogin = $fromLogin;

        return $this;
    }

    public function isShowSocials(): ?bool
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

    public function isEnabled(): ?bool
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

    public function isVerified(): ?bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;

        return $this;
    }

    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }
}
