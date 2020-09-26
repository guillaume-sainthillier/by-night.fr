<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Parser\Common\DigitickAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Reject\Reject;
use App\Validator\Constraints\EventConstraint;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\Type;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ORM\Table(indexes={
 *     @ORM\Index(name="event_slug_idx", columns={"slug"}),
 *     @ORM\Index(name="event_date_debut_idx", columns={"date_debut"}),
 *     @ORM\Index(name="event_theme_manifestation_idx", columns={"theme_manifestation"}),
 *     @ORM\Index(name="event_type_manifestation_idx", columns={"type_manifestation"}),
 *     @ORM\Index(name="event_categorie_manifestation_idx", columns={"categorie_manifestation"}),
 *     @ORM\Index(name="event_search_idx", columns={"place_id", "date_fin", "date_debut"}),
 *     @ORM\Index(name="event_top_soiree_idx", columns={"date_fin", "participations"}),
 *     @ORM\Index(name="event_external_id_idx", columns={"external_id"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\EventRepository")
 * @ORM\HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 * @Vich\Uploadable
 * @EventConstraint
 */
class Event
{
    const INDEX_FROM = '-6 months';

    use EntityTimestampableTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"list_event"})
     * @Expose
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $externalId = null;

    /**
     * @Gedmo\Slug(fields={"nom"})
     * @ORM\Column(length=255)
     */
    private ?string $slug = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de nommer votre événement !")
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $nom = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de décrire votre événement !")
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $descriptif = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?DateTimeInterface $externalUpdatedAt = null;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Assert\NotBlank(message="Vous devez donner une date à votre événement")
     * @Groups({"list_event"})
     * @Expose
     * @Type("DateTime<'Y-m-d'>")
     */
    private ?DateTimeInterface $dateDebut = null;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     * @Type("DateTime<'Y-m-d'>")
     */
    private ?DateTimeInterface $dateFin = null;

    /**
     * @ORM\Column(type="string", length=256, nullable=true)
     */
    private ?string $horaires = null;

    /**
     * @ORM\Column(type="string", length=16, nullable=true)
     */
    private ?string $modificationDerniereMinute = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?float $latitude = null;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?float $longitude = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $adresse = null;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $typeManifestation = null;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $categorieManifestation = null;

    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $themeManifestation = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $phoneContacts = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $mailContacts = null;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private ?array $websiteContacts = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $reservationTelephone = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Email
     */
    private ?string $reservationEmail = null;

    /**
     * @ORM\Column(type="string", length=512, nullable=true)
     * @Assert\Url
     */
    private ?string $reservationInternet = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $tarif = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $fromData = null;

    /**
     * @ORM\Column(type="string", length=7, nullable=true)
     */
    private ?string $parserVersion = null;

    /**
     * @Vich\UploadableField(mapping="event_image", fileNameProperty="image.name", size="image.size", mimeType="image.mimeType", originalName="image.originalName", dimensions="image.dimensions")
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
     * @Vich\UploadableField(mapping="event_image", fileNameProperty="imageSystem.name", size="imageSystem.size", mimeType="imageSystem.mimeType", originalName="imageSystem.originalName", dimensions="imageSystem.dimensions")
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

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $name = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $url = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?User $user = null;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"list_event"})
     * @Expose
     */
    private bool $brouillon = false;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $tweetPostId = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $facebookEventId = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $tweetPostSystemId = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $fbPostId = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $fbPostSystemId = null;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\UserEvent", mappedBy="event", cascade={"persist", "merge", "remove"}, fetch="EXTRA_LAZY")
     */
    protected Collection $userEvents;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Comment", mappedBy="event", cascade={"persist", "merge", "remove"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"createdAt": "DESC"})
     */
    protected Collection $commentaires;

    /**
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    private ?string $facebookOwnerId = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?int $fbParticipations = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $fbInterets = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $participations = null;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $interets = null;

    /**
     * @ORM\Column(type="string", length=256, nullable=true)
     */
    private ?string $source = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Place", cascade={"persist", "merge"})
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"list_event"})
     * @Expose
     * @Assert\Valid
     */
    private ?Place $place = null;

    private ?Reject $reject = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $archive = false;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="Vous devez indiquer le lieu de votre événement")
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $placeName = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $placeStreet = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $placeCity = null;

    /**
     * @ORM\Column(type="string", length=7, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private ?string $placePostalCode = null;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    private ?string $placeExternalId = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $placeFacebookId = null;

    private ?Reject $placeReject = null;

    private ?string $placeCountryName = null;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     * @ORM\JoinColumn(nullable=true)
     */
    private ?Country $placeCountry = null;

    public function __construct()
    {
        $this->dateDebut = new DateTime();
        $this->userEvents = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->image = new EmbeddedFile();
        $this->imageSystem = new EmbeddedFile();
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

    public function isIndexable()
    {
        $from = new DateTime();
        $from->modify(self::INDEX_FROM);

        return $this->dateFin >= $from;
    }

    public function isAffiliate(): bool
    {
        return \in_array($this->fromData, [FnacSpectaclesAwinParser::getParserName(), DigitickAwinParser::getParserName()], true);
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

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function majDateFin()
    {
        if (null === $this->dateFin) {
            $this->dateFin = $this->dateDebut;
        }
    }

    public function getLocationSlug()
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

    public function getDistinctTags()
    {
        $tags = $this->categorieManifestation . ',' . $this->typeManifestation . ',' . $this->themeManifestation;

        return \array_unique(\array_map('trim', \array_map('ucfirst', \array_filter(\preg_split('#[,/]#', $tags)))));
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

    public function __toString()
    {
        return sprintf('%s (#%s)',
            $this->nom,
            $this->id
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id)
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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescriptif(): ?string
    {
        return $this->descriptif;
    }

    public function setDescriptif(?string $descriptif): self
    {
        $this->descriptif = $descriptif;

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

    public function getDateDebut(): ?DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    public function getHoraires(): ?string
    {
        return $this->horaires;
    }

    public function setHoraires(?string $horaires): self
    {
        $this->horaires = $horaires;

        return $this;
    }

    public function getModificationDerniereMinute(): ?string
    {
        return $this->modificationDerniereMinute;
    }

    public function setModificationDerniereMinute(?string $modificationDerniereMinute): self
    {
        $this->modificationDerniereMinute = $modificationDerniereMinute;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTypeManifestation(): ?string
    {
        return $this->typeManifestation;
    }

    public function setTypeManifestation(?string $typeManifestation): self
    {
        $this->typeManifestation = $typeManifestation;

        return $this;
    }

    public function getCategorieManifestation(): ?string
    {
        return $this->categorieManifestation;
    }

    public function setCategorieManifestation(?string $categorieManifestation): self
    {
        $this->categorieManifestation = $categorieManifestation;

        return $this;
    }

    public function getThemeManifestation(): ?string
    {
        return $this->themeManifestation;
    }

    public function setThemeManifestation(?string $themeManifestation): self
    {
        $this->themeManifestation = $themeManifestation;

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

    public function getTarif(): ?string
    {
        return $this->tarif;
    }

    public function setTarif(?string $tarif): self
    {
        $this->tarif = $tarif;

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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

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

    public function setPlaceName(string $placeName): self
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
     * @return Collection|UserEvent[]
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
     * @return Collection|Comment[]
     */
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function addCommentaire(Comment $commentaire): self
    {
        if (!$this->commentaires->contains($commentaire)) {
            $this->commentaires[] = $commentaire;
            $commentaire->setEvent($this);
        }

        return $this;
    }

    public function removeCommentaire(Comment $commentaire): self
    {
        if ($this->commentaires->contains($commentaire)) {
            $this->commentaires->removeElement($commentaire);
            // set the owning side to null (unless already changed)
            if ($commentaire->getEvent() === $this) {
                $commentaire->setEvent(null);
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

    public function getBrouillon(): ?bool
    {
        return $this->brouillon;
    }

    public function setBrouillon(bool $brouillon): self
    {
        $this->brouillon = $brouillon;

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
}
