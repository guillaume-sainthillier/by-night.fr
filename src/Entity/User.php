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
use App\Picture\ImageFormats;
use App\Repository\UserRepository;
use App\Security\TwoFactor\TwoFactorProviders;
use App\Utils\ObjectKey;
use App\Utils\UnitOfWorkOptimizer;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use FOS\ElasticaBundle\Doctrine\ConditionalUpdate;
use Gedmo\Mapping\Annotation as Gedmo;
use Jbtronics\TFAWebauthn\Model\NoLegacyU2FKeys;
use Jbtronics\TFAWebauthn\Model\TwoFactorInterface as WebauthnTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\BackupCodeInterface;
use Scheb\TwoFactorBundle\Model\Google\TwoFactorInterface as GoogleTwoFactorInterface;
use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Serializable;
use SortDirection;
use Stringable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Webauthn\PublicKeyCredentialUserEntity;

#[Vich\Uploadable]
#[UniqueEntity(fields: ['email'], message: 'Un utilisateur existe déjà pour cette adresse e-mail')]
#[UniqueEntity(fields: ['username'], message: "Ce nom d'utilisateur est déjà pris.")]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Table(name: '`user`')]
#[ORM\Index(name: 'user_image_name_idx', columns: ['image_name'])]
#[ORM\Index(name: 'user_image_system_name_idx', columns: ['image_system_name'])]
#[ORM\UniqueConstraint(name: 'user_calendar_token_unique', columns: ['calendar_token'])]
#[ORM\EntityListeners([UserEmailEntityListener::class])]
class User implements UserInterface, PasswordAuthenticatedUserInterface, Serializable, Stringable, InternalIdentifiableInterface, PrefixableObjectKeyInterface, ConditionalUpdate, GoogleTwoFactorInterface, WebauthnTwoFactorInterface, BackupCodeInterface, PreferredProviderInterface
{
    use EntityTimestampableTrait;
    use NoLegacyU2FKeys;

    /** The prefix of this entity's keys and of its DTO's, see ObjectKey */
    final public const string KEY_PREFIX = 'user';

    /** The domains of the addresses made up for the former Twitter and Facebook logins: no mail server behind them */
    final public const array UNREACHABLE_EMAIL_DOMAINS = ['twitter.fr', 'facebook.fr'];

    /**
     * The addresses last logged in before that day hard-bounced ten times as often as the later ones (the publishers'
     * news of 2026-10-24): a login since makes them reachable again.
     */
    final public const string REACHABLE_SINCE = '2018-01-01';

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    #[Groups(['elasticsearch:event:details', 'elasticsearch:user:details'])]
    private ?int $id = null;

    #[Assert\Length(max: 180)]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $salt = null;

    #[Assert\Length(max: 180)]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Groups(['elasticsearch:user:details'])]
    private ?string $username = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $enabled = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastLogin;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $passwordRequestedAt = null;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING)]
    private ?string $password = null;

    #[ORM\Column(length: 128, unique: true)]
    #[Gedmo\Slug(fields: ['username'])]
    private ?string $slug = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $firstname = null;

    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
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

    /** The secret of the authenticator app: null while the TOTP second factor is off */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $googleAuthenticatorSecret = null;

    /**
     * The recovery codes not used yet, each usable once in place of the second factor
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $backupCodes = null;

    /**
     * @var Collection<int, WebauthnKey>
     */
    #[ORM\OneToMany(targetEntity: WebauthnKey::class, mappedBy: 'user', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => SortDirection::Ascending])]
    private Collection $webauthnKeys;

    /** The city the member goes out in, chosen on their profile: the location of the pages whose URL has none */
    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?City $city = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private bool $fromLogin = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private bool $showSocials = true;

    #[Assert\Url(requireTld: false)]
    #[Assert\Length(max: 255)]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $website = null;

    #[Vich\UploadableField(mapping: 'user_image', fileNameProperty: 'image.name', size: 'image.size', mimeType: 'image.mimeType', originalName: 'image.originalName', dimensions: 'image.dimensions')]
    #[Assert\Valid]
    #[Assert\File(maxSize: ImageFormats::MAX_SIZE)]
    #[Assert\Image(mimeTypes: ImageFormats::MIME_TYPES)]
    private ?File $imageFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $image;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $imageHash = null;

    #[Vich\UploadableField(mapping: 'user_image', fileNameProperty: 'imageSystem.name', size: 'imageSystem.size', mimeType: 'imageSystem.mimeType', originalName: 'imageSystem.originalName', dimensions: 'imageSystem.dimensions')]
    #[Assert\Valid]
    #[Assert\Image(maxSize: ImageFormats::MAX_SIZE, mimeTypes: ImageFormats::MIME_TYPES)]
    private ?File $imageSystemFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $imageSystem;

    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $imageSystemHash = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $verified = false;

    /** Whether the member asked for the weekend selection of their city by e-mail: never without their consent */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $weekendDigest = false;

    /** When the member last asked for the weekend selection: the proof of their consent */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $weekendDigestSince = null;

    /** The ISO week ("2026-W43") of the last weekend selection sent to the member, which a second run skips */
    #[ORM\Column(type: Types::STRING, length: 8, nullable: true)]
    private ?string $weekendDigestSentWeek = null;

    /**
     * The secret of the address of the member's outings as a calendar (OutingCalendarController): whoever has it reads
     * them, so the member can change it. Made the first time the member's outings show it.
     */
    #[ORM\Column(type: Types::STRING, length: 32, nullable: true)]
    private ?string $calendarToken = null;

    /** Whether the member hears by e-mail of the changes of their outings (OutingChangeNotifier) */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $outingAlerts = true;

    /** Whether the member, when they run a publisher, gets the news By Night sends them (app:publishers:send-news) */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $publisherNews = true;

    private bool $sendToElasticsearch = false;

    public function __construct()
    {
        $this->lastLogin = new DateTimeImmutable();
        $this->userEvents = new ArrayCollection();
        $this->webauthnKeys = new ArrayCollection();
        $this->oAuth = new UserOAuth();
        $this->image = new EmbeddedFile();
        $this->imageSystem = new EmbeddedFile();
    }

    /**
     * Only send to Elasticsearch when indexed fields change.
     *
     * @see fos_elastica.yaml user index mapping (username)
     */
    public function shouldBeUpdated(): bool
    {
        return $this->sendToElasticsearch;
    }

    public function getInternalId(): ?string
    {
        if (null === $this->getId()) {
            return null;
        }

        return ObjectKey::internal(self::KEY_PREFIX, $this->getId());
    }

    public function getKeyPrefix(): string
    {
        return self::KEY_PREFIX;
    }

    public function hasImage(): bool
    {
        return (null !== $this->image->getName() && '' !== $this->image->getName())
            || (null !== $this->imageSystem->getName() && '' !== $this->imageSystem->getName());
    }

    /**
     * How complete the profile is, in percent: the picture, the names, the presentation and a verified e-mail address.
     */
    public function getProfileCompletion(): int
    {
        // Empty strings (older rows) count as not filled, like null
        $fields = [
            $this->hasImage(),
            $this->firstname,
            $this->lastname,
            $this->description,
            $this->verified,
        ];

        return (int) round(100 * \count(array_filter($fields)) / \count($fields));
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
    public function setImageSystemFile(?File $image): self
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
        // As the member typed it: they choose it at sign-up
        return $this->username ?? '';
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
        // If you store any temporary, sensitive data on the user, clear it here
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

    public function isPublisherNews(): bool
    {
        return $this->publisherNews;
    }

    public function setPublisherNews(bool $publisherNews): self
    {
        $this->publisherNews = $publisherNews;

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

    public function getCalendarToken(): ?string
    {
        return $this->calendarToken;
    }

    /**
     * A new address for the member's outings as a calendar: the former one no longer answers.
     */
    public function renewCalendarToken(): self
    {
        $this->calendarToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');

        return $this;
    }

    public function isOutingAlerts(): bool
    {
        return $this->outingAlerts;
    }

    public function setOutingAlerts(bool $outingAlerts): self
    {
        $this->outingAlerts = $outingAlerts;

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

    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Whether the e-mails the site sends on its own (the outing alerts, the weekend selection) may reach the member:
     * not to an address made up for a former social login, nor to one not logged in since REACHABLE_SINCE.
     * UserRepository::andWhereDeliverableEmail() is the same check in a query. The publishers' news unsubscribed the
     * same members once instead (Version20261024101431).
     */
    public function hasDeliverableEmail(): bool
    {
        if (null === $this->email || '' === $this->email) {
            return false;
        }

        $domain = mb_strtolower(substr((string) strrchr($this->email, '@'), 1));
        if (\in_array($domain, self::UNREACHABLE_EMAIL_DOMAINS, true)) {
            return false;
        }

        return null !== $this->lastLogin && $this->lastLogin >= new DateTimeImmutable(self::REACHABLE_SINCE);
    }

    public function setUsername(?string $username): self
    {
        if ($this->username !== $username) {
            $this->sendToElasticsearch = true;
        }

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

    public function getLastLogin(): ?DateTimeImmutable
    {
        return $this->lastLogin;
    }

    public function setLastLogin(?DateTimeImmutable $lastLogin): self
    {
        $this->lastLogin = UnitOfWorkOptimizer::getDateTimeValue($this->lastLogin, $lastLogin);

        return $this;
    }

    public function getPasswordRequestedAt(): ?DateTimeImmutable
    {
        return $this->passwordRequestedAt;
    }

    public function setPasswordRequestedAt(?DateTimeImmutable $passwordRequestedAt): self
    {
        $this->passwordRequestedAt = UnitOfWorkOptimizer::getDateTimeValue($this->passwordRequestedAt, $passwordRequestedAt);

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

    public function isWeekendDigest(): bool
    {
        return $this->weekendDigest;
    }

    /**
     * Asking for it again dates the consent anew; giving it up forgets the date.
     */
    public function setWeekendDigest(bool $weekendDigest): self
    {
        if ($weekendDigest !== $this->weekendDigest) {
            $this->weekendDigestSince = $weekendDigest ? new DateTimeImmutable() : null;
        }

        $this->weekendDigest = $weekendDigest;

        return $this;
    }

    public function getWeekendDigestSince(): ?DateTimeImmutable
    {
        return $this->weekendDigestSince;
    }

    public function getWeekendDigestSentWeek(): ?string
    {
        return $this->weekendDigestSentWeek;
    }

    public function setWeekendDigestSentWeek(?string $weekendDigestSentWeek): self
    {
        $this->weekendDigestSentWeek = $weekendDigestSentWeek;

        return $this;
    }

    /**
     * The weekend selection is the one of the member's city: they choose it in the same form.
     */
    #[Assert\Callback]
    public function validateWeekendDigest(ExecutionContextInterface $context): void
    {
        if ($this->weekendDigest && null === $this->city) {
            $context
                ->buildViolation('Veuillez choisir votre ville pour recevoir la sélection du week-end.')
                ->atPath('city')
                ->addViolation();
        }
    }

    public function setSalt(?string $salt): self
    {
        $this->salt = $salt;

        return $this;
    }

    public function isGoogleAuthenticatorEnabled(): bool
    {
        return null !== $this->googleAuthenticatorSecret && '' !== $this->googleAuthenticatorSecret;
    }

    public function getGoogleAuthenticatorUsername(): string
    {
        return $this->email ?? $this->username ?? '';
    }

    public function getGoogleAuthenticatorSecret(): ?string
    {
        return $this->googleAuthenticatorSecret;
    }

    public function setGoogleAuthenticatorSecret(?string $googleAuthenticatorSecret): self
    {
        $this->googleAuthenticatorSecret = $googleAuthenticatorSecret;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    /**
     * @param list<string>|null $backupCodes
     */
    public function setBackupCodes(?array $backupCodes): self
    {
        $this->backupCodes = $backupCodes;

        return $this;
    }

    public function isBackupCodeEnabled(): bool
    {
        return [] !== ($this->backupCodes ?? []);
    }

    public function isBackupCode(string $code): bool
    {
        return \in_array($code, $this->backupCodes ?? [], true);
    }

    /**
     * Re-indexed: the codes must stay a JSON list, not become an object once one is gone
     */
    public function invalidateBackupCode(string $code): void
    {
        $this->backupCodes = array_values(array_filter(
            $this->backupCodes ?? [],
            static fn (string $backupCode): bool => $backupCode !== $code,
        ));
    }

    /**
     * @return Collection<int, WebauthnKey>
     */
    public function getWebauthnKeys(): Collection
    {
        return $this->webauthnKeys;
    }

    public function addWebauthnKey(WebauthnKey $webauthnKey): self
    {
        if (!$this->webauthnKeys->contains($webauthnKey)) {
            $this->webauthnKeys[] = $webauthnKey;
            $webauthnKey->setUser($this);
        }

        return $this;
    }

    public function removeWebauthnKey(WebauthnKey $webauthnKey): self
    {
        $this->webauthnKeys->removeElement($webauthnKey);

        return $this;
    }

    public function isWebAuthnAuthenticatorEnabled(): bool
    {
        return !$this->webauthnKeys->isEmpty();
    }

    /**
     * Which passkeys the account has, signed into its remember-me cookies (config/packages/security.yaml): adding or
     * removing one invalidates them. Their UUIDs, not their signature counters, which change on every use
     */
    public function getWebauthnKeysFingerprint(): string
    {
        $uuids = $this->webauthnKeys->map(static fn (WebauthnKey $key): string => $key->getUuid()->toString())->toArray();
        sort($uuids);

        return implode(',', $uuids);
    }

    /**
     * The account the passkeys are bound to: its handle is the id, never the e-mail, which can change
     */
    public function getWebAuthnUser(): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create(
            $this->getUserIdentifier(),
            (string) $this->getId(),
            $this->getUsername(),
        );
    }

    public function hasSecondFactor(): bool
    {
        return $this->isGoogleAuthenticatorEnabled() || $this->isWebAuthnAuthenticatorEnabled();
    }

    /**
     * The 2FA form opens on the passkey when there is one (App\Security\TwoFactor\TwoFactorProviderSelectionListener)
     */
    public function getPreferredTwoFactorProvider(): ?string
    {
        return $this->isWebAuthnAuthenticatorEnabled() ? TwoFactorProviders::WEBAUTHN : null;
    }
}
