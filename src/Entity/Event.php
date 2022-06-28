<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Contracts\ExternalIdentifiableInterface;
use App\Contracts\InternalIdentifiableInterface;
use App\Contracts\PrefixableObjectKeyInterface;
use App\Parser\Common\DigitickAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Reject\Reject;
use App\Repository\EventRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\Type;
use Stringable;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[ORM\Index(name: 'event_slug_idx', columns: ['slug'])]
#[ORM\Index(name: 'event_start_date_idx', columns: ['start_date'])]
#[ORM\Index(name: 'event_theme_idx', columns: ['theme'])]
#[ORM\Index(name: 'event_type_idx', columns: ['type'])]
#[ORM\Index(name: 'event_category_idx', columns: ['category'])]
#[ORM\Index(name: 'event_search_idx', columns: ['place_id', 'end_date', 'start_date'])]
#[ORM\Index(name: 'event_top_soiree_idx', columns: ['end_date', 'participations'])]
#[ORM\Index(name: 'event_external_id_idx', columns: ['external_id', 'external_origin'])]
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: '`event`')]
#[ORM\HasLifecycleCallbacks]
class Event implements Stringable, ExternalIdentifiableInterface, InternalIdentifiableInterface, PrefixableObjectKeyInterface
{
    use EntityTimestampableTrait;

    /**
     * @var string
     */
    public const INDEX_FROM = '-6 months';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Groups(['elasticsearch:event:details'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'string', length: 63, nullable: true)]
    private ?string $externalOrigin = null;

    #[ORM\Column(length: 255)]
    #[Gedmo\Slug(fields: ['name'], unique: false)]
    private ?string $slug = null;

    #[Assert\NotBlank(message: "N'oubliez pas de décrire votre événement !")]
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $externalUpdatedAt = null;

    #[Assert\NotBlank(message: 'Vous devez donner une date à votre événement')]
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    #[Type("DateTime<'Y-m-d'>")]
    private ?DateTimeInterface $startDate;

    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    #[Type("DateTime<'Y-m-d'>")]
    private ?DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'string', length: 256, nullable: true)]
    private ?string $hours = null;

    #[ORM\Column(type: 'string', length: 16, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?float $longitude = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $category = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $theme = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $phoneContacts = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $mailContacts = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $websiteContacts = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reservationTelephone = null;

    /** @deprecated  */
    #[Assert\Email]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reservationEmail = null;

    /** @deprecated  */
    #[Assert\Url]
    #[ORM\Column(type: 'string', length: 512, nullable: true)]
    private ?string $reservationInternet = null;

    /** @deprecated  */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $prices = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $fromData = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $parserVersion = null;

    #[Vich\UploadableField(mapping: 'event_image', fileNameProperty: 'image.name', size: 'image.size', mimeType: 'image.mimeType', originalName: 'image.originalName', dimensions: 'image.dimensions')]
    #[Assert\Valid]
    #[Assert\File(maxSize: '6M')]
    #[Assert\Image]
    private ?File $imageFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $image;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $imageHash = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $imageMainColor = null;

    #[Vich\UploadableField(mapping: 'event_image', fileNameProperty: 'imageSystem.name', size: 'imageSystem.size', mimeType: 'imageSystem.mimeType', originalName: 'imageSystem.originalName', dimensions: 'imageSystem.dimensions')]
    #[Assert\Valid]
    #[Assert\Image(maxSize: '6M')]
    private ?File $imageSystemFile = null;

    #[ORM\Embedded(class: EmbeddedFile::class)]
    private EmbeddedFile $imageSystem;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $imageSystemHash = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $imageSystemMainColor = null;

    #[Assert\NotBlank(message: "N'oubliez pas de nommer votre événement !")]
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['elasticsearch:event:details'])]
    private bool $draft = false;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $tweetPostId = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $facebookEventId = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $tweetPostSystemId = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $fbPostId = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $fbPostSystemId = null;

    /**
     * @var Collection<int, UserEvent>
     */
    #[ORM\OneToMany(targetEntity: UserEvent::class, mappedBy: 'event', cascade: ['persist', 'merge', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $userEvents;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(targetEntity: Comment::class, mappedBy: 'event', cascade: ['persist', 'merge', 'remove'], fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $comments;

    #[ORM\Column(type: 'string', length: 31, nullable: true)]
    private ?string $facebookOwnerId = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fbParticipations = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fbInterets = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $participations = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $interets = null;

    #[ORM\Column(type: 'string', length: 256, nullable: true)]
    private ?string $source = null;

    #[Assert\Valid]
    #[ORM\ManyToOne(targetEntity: Place::class, cascade: ['persist', 'merge'])]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['elasticsearch:event:details'])]
    private ?Place $place = null;

    private ?Reject $reject = null;

    #[ORM\Column(type: 'boolean')]
    private bool $archive = false;

    #[Assert\NotBlank(message: 'Vous devez indiquer le lieu de votre événement')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $placeName = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $placeStreet = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $placeCity = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Groups(['elasticsearch:event:details'])]
    private ?string $placePostalCode = null;

    #[ORM\Column(type: 'string', length: 127, nullable: true)]
    private ?string $placeExternalId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $placeFacebookId = null;

    private ?Reject $placeReject = null;

    private ?string $placeCountryName = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Country $placeCountry = null;

    public function __construct()
    {
        $this->startDate = new DateTime();
        $this->userEvents = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->image = new EmbeddedFile();
        $this->imageSystem = new EmbeddedFile();
    }

    public function getKeyPrefix(): string
    {
        return 'event';
    }

    public function getInternalId(): ?string
    {
        if (null === $this->getId()) {
            return null;
        }

        return sprintf(
            '%s-id-%d',
            $this->getKeyPrefix(),
            $this->getId()
        );
    }

    public function hasImage(): bool
    {
        return (null !== $this->image->getName() && '' !== $this->image->getName())
            || (null !== $this->imageSystem->getName() && '' !== $this->imageSystem->getName());
    }

    public function getReject(): ?Reject
    {
        return $this->reject;
    }

    public function setReject(?Reject $reject): self
    {
        $this->reject = $reject;

        return $this;
    }

    public function getPlaceReject(): ?Reject
    {
        return $this->placeReject;
    }

    public function setPlaceReject(?Reject $placeReject): self
    {
        $this->placeReject = $placeReject;

        return $this;
    }

    public function isIndexable(): bool
    {
        if (false !== $this->draft) {
            return false;
        }

        $from = new DateTime();
        $from->modify(self::INDEX_FROM);

        return $this->endDate >= $from;
    }

    public function isAffiliate(): bool
    {
        return \in_array($this->fromData, [
            FnacSpectaclesAwinParser::getParserName(),
            DigitickAwinParser::getParserName(),
        ], true);
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

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function majEndDate(): void
    {
        if (null === $this->endDate) {
            $this->endDate = $this->startDate;
        }
    }

    public function getLocationSlug(): ?string
    {
        if ($this->getPlace() && $this->getPlace()->getCity()) {
            return $this->getPlace()->getCity()->getSlug();
        }

        if ($this->getPlace() && $this->getPlace()->getCountry()) {
            return $this->getPlace()->getCountry()->getSlug();
        }

        return 'unknown';
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): self
    {
        $this->place = $place;

        return $this;
    }

    /**
     * @return string[]
     *
     * @psalm-return array<int, string>
     */
    public function getDistinctTags(): array
    {
        $tags = $this->category . ',' . $this->type . ',' . $this->theme;

        return array_unique(array_map('trim', array_map('ucfirst', array_filter(preg_split('#[,/]#', $tags)))));
    }

    public function getPlaceCountryName(): ?string
    {
        return $this->placeCountryName;
    }

    public function setPlaceCountryName(?string $placeCountryName): self
    {
        $this->placeCountryName = $placeCountryName;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s (#%s)',
            $this->name,
            $this->id
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function getExternalUpdatedAt(): ?DateTimeInterface
    {
        return $this->externalUpdatedAt;
    }

    public function setExternalUpdatedAt(?DateTimeInterface $externalUpdatedAt): self
    {
        $this->externalUpdatedAt = $externalUpdatedAt;

        return $this;
    }

    public function getStartDate(): ?DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getHours(): ?string
    {
        return $this->hours;
    }

    public function setHours(?string $hours): self
    {
        $this->hours = $hours;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): self
    {
        $this->theme = $theme;

        return $this;
    }

    public function getReservationTelephone(): ?string
    {
        return $this->reservationTelephone;
    }

    public function setReservationTelephone(?string $reservationTelephone): self
    {
        $this->reservationTelephone = $reservationTelephone;

        return $this;
    }

    public function getReservationEmail(): ?string
    {
        return $this->reservationEmail;
    }

    public function setReservationEmail(?string $reservationEmail): self
    {
        $this->reservationEmail = $reservationEmail;

        return $this;
    }

    public function getReservationInternet(): ?string
    {
        return $this->reservationInternet;
    }

    public function setReservationInternet(?string $reservationInternet): self
    {
        $this->reservationInternet = $reservationInternet;

        return $this;
    }

    public function getPrices(): ?string
    {
        return $this->prices;
    }

    public function setPrices(?string $prices): self
    {
        $this->prices = $prices;

        return $this;
    }

    public function getFromData(): ?string
    {
        return $this->fromData;
    }

    public function setFromData(?string $fromData): self
    {
        $this->fromData = $fromData;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getTweetPostId(): ?string
    {
        return $this->tweetPostId;
    }

    public function setTweetPostId(?string $tweetPostId): self
    {
        $this->tweetPostId = $tweetPostId;

        return $this;
    }

    public function getFacebookEventId(): ?string
    {
        return $this->facebookEventId;
    }

    public function setFacebookEventId(?string $facebookEventId): self
    {
        $this->facebookEventId = $facebookEventId;

        return $this;
    }

    public function getTweetPostSystemId(): ?string
    {
        return $this->tweetPostSystemId;
    }

    public function setTweetPostSystemId(?string $tweetPostSystemId): self
    {
        $this->tweetPostSystemId = $tweetPostSystemId;

        return $this;
    }

    public function getFbPostId(): ?string
    {
        return $this->fbPostId;
    }

    public function setFbPostId(?string $fbPostId): self
    {
        $this->fbPostId = $fbPostId;

        return $this;
    }

    public function getFbPostSystemId(): ?string
    {
        return $this->fbPostSystemId;
    }

    public function setFbPostSystemId(?string $fbPostSystemId): self
    {
        $this->fbPostSystemId = $fbPostSystemId;

        return $this;
    }

    public function getFacebookOwnerId(): ?string
    {
        return $this->facebookOwnerId;
    }

    public function setFacebookOwnerId(?string $facebookOwnerId): self
    {
        $this->facebookOwnerId = $facebookOwnerId;

        return $this;
    }

    public function getFbParticipations(): ?int
    {
        return $this->fbParticipations;
    }

    public function setFbParticipations(?int $fbParticipations): self
    {
        $this->fbParticipations = $fbParticipations;

        return $this;
    }

    public function getFbInterets(): ?int
    {
        return $this->fbInterets;
    }

    public function setFbInterets(?int $fbInterets): self
    {
        $this->fbInterets = $fbInterets;

        return $this;
    }

    public function getParticipations(): ?int
    {
        return $this->participations;
    }

    public function setParticipations(?int $participations): self
    {
        $this->participations = $participations;

        return $this;
    }

    public function getInterets(): ?int
    {
        return $this->interets;
    }

    public function setInterets(?int $interets): self
    {
        $this->interets = $interets;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getPlaceName(): ?string
    {
        return $this->placeName;
    }

    public function setPlaceName(?string $placeName): self
    {
        $this->placeName = $placeName;

        return $this;
    }

    public function getPlaceStreet(): ?string
    {
        return $this->placeStreet;
    }

    public function setPlaceStreet(?string $placeStreet): self
    {
        $this->placeStreet = $placeStreet;

        return $this;
    }

    public function getPlaceCity(): ?string
    {
        return $this->placeCity;
    }

    public function setPlaceCity(?string $placeCity): self
    {
        $this->placeCity = $placeCity;

        return $this;
    }

    public function getPlacePostalCode(): ?string
    {
        return $this->placePostalCode;
    }

    public function setPlacePostalCode(?string $placePostalCode): self
    {
        $this->placePostalCode = $placePostalCode;

        return $this;
    }

    public function getPlaceExternalId(): ?string
    {
        return $this->placeExternalId;
    }

    public function setPlaceExternalId(?string $placeExternalId): self
    {
        $this->placeExternalId = $placeExternalId;

        return $this;
    }

    public function getPlaceFacebookId(): ?string
    {
        return $this->placeFacebookId;
    }

    public function setPlaceFacebookId(?string $placeFacebookId): self
    {
        $this->placeFacebookId = $placeFacebookId;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

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
            $userEvent->setEvent($this);
        }

        return $this;
    }

    public function removeUserEvent(UserEvent $userEvent): self
    {
        if ($this->userEvents->contains($userEvent)) {
            $this->userEvents->removeElement($userEvent);
            // set the owning side to null (unless already changed)
            if ($userEvent->getEvent() === $this) {
                $userEvent->setEvent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments[] = $comment;
            $comment->setEvent($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): self
    {
        if ($this->comments->contains($comment)) {
            $this->comments->removeElement($comment);
            // set the owning side to null (unless already changed)
            if ($comment->getEvent() === $this) {
                $comment->setEvent(null);
            }
        }

        return $this;
    }

    public function getPlaceCountry(): ?Country
    {
        return $this->placeCountry;
    }

    public function setPlaceCountry(?Country $placeCountry): self
    {
        $this->placeCountry = $placeCountry;

        return $this;
    }

    public function getDraft(): ?bool
    {
        return $this->draft;
    }

    public function setDraft(bool $draft): self
    {
        $this->draft = $draft;

        return $this;
    }

    public function getArchive(): ?bool
    {
        return $this->archive;
    }

    public function setArchive(bool $archive): self
    {
        $this->archive = $archive;

        return $this;
    }

    public function getParserVersion(): ?string
    {
        return $this->parserVersion;
    }

    public function setParserVersion(?string $parserVersion): self
    {
        $this->parserVersion = $parserVersion;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

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

    public function getPhoneContacts(): ?array
    {
        return $this->phoneContacts;
    }

    public function setPhoneContacts(?array $phoneContacts): self
    {
        $this->phoneContacts = $phoneContacts;

        return $this;
    }

    public function getMailContacts(): ?array
    {
        return $this->mailContacts;
    }

    public function setMailContacts(?array $mailContacts): self
    {
        $this->mailContacts = $mailContacts;

        return $this;
    }

    public function getWebsiteContacts(): ?array
    {
        return $this->websiteContacts;
    }

    public function setWebsiteContacts(?array $websiteContacts): self
    {
        $this->websiteContacts = $websiteContacts;

        return $this;
    }

    public function getExternalOrigin(): ?string
    {
        return $this->externalOrigin;
    }

    public function setExternalOrigin(?string $externalOrigin): self
    {
        $this->externalOrigin = $externalOrigin;

        return $this;
    }

    public function getImageMainColor(): ?string
    {
        return $this->imageMainColor;
    }

    public function setImageMainColor(?string $imageMainColor): self
    {
        $this->imageMainColor = $imageMainColor;

        return $this;
    }

    public function getImageSystemMainColor(): ?string
    {
        return $this->imageSystemMainColor;
    }

    public function setImageSystemMainColor(?string $imageSystemMainColor): self
    {
        $this->imageSystemMainColor = $imageSystemMainColor;

        return $this;
    }

    public function isDraft(): ?bool
    {
        return $this->draft;
    }

    public function isArchive(): ?bool
    {
        return $this->archive;
    }
}
