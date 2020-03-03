<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * Event.
 *
 * @ORM\Table(name="Agenda", indexes={
 *     @ORM\Index(name="event_slug_idx", columns={"slug"}),
 *     @ORM\Index(name="event_date_debut_idx", columns={"date_debut"}),
 *     @ORM\Index(name="event_theme_manifestation_idx", columns={"theme_manifestation"}),
 *     @ORM\Index(name="event_type_manifestation_idx", columns={"type_manifestation"}),
 *     @ORM\Index(name="event_categorie_manifestation_idx", columns={"categorie_manifestation"}),
 *     @ORM\Index(name="event_search_idx", columns={"place_id", "date_fin", "date_debut"}),
 *     @ORM\Index(name="event_top_soiree_idx", columns={"date_fin", "participations"}),
 *     @ORM\Index(name="event_external_id_idx", columns={"external_id"})
 * })
 *
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
    protected $id;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $externalId;

    /**
     * @Gedmo\Slug(fields={"nom"}, unique=false)
     * @ORM\Column(length=255)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de nommer votre événement !")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $nom;

    /**
     * @var string
     *
     * @ORM\Column(type="text", nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de décrire votre événement !")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $descriptif;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $externalUpdatedAt;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="date", nullable=true)
     * @Assert\NotBlank(message="Vous devez donner une date à votre événement")
     * @Groups({"list_event"})
     * @Expose
     * @Type("DateTime<'Y-m-d'>")
     */
    protected $dateDebut;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="date", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     * @Type("DateTime<'Y-m-d'>")
     */
    protected $dateFin;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=256, nullable=true)
     */
    protected $horaires;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=16, nullable=true)
     */
    protected $modificationDerniereMinute;

    /**
     * @var float
     *
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $latitude;

    /**
     * @var float
     *
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $longitude;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $adresse;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $typeManifestation;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $categorieManifestation;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $themeManifestation;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $reservationTelephone;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Email
     */
    protected $reservationEmail;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=512, nullable=true)
     * @Assert\Url
     */
    protected $reservationInternet;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $tarif;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $fromData;

    /**
     * @var string
     * @ORM\Column(type="string", length=7, nullable=true)
     */
    protected $parserVersion;

    /**
     * @Vich\UploadableField(mapping="event_image", fileNameProperty="path")
     * @Assert\Valid
     * @Assert\File(maxSize="6M")
     * @Assert\Image
     */
    protected $file;

    /**
     * @Vich\UploadableField(mapping="event_image", fileNameProperty="systemPath")
     * @Assert\Valid
     * @Assert\File(maxSize="6M")
     * @Assert\Image
     */
    protected $systemFile;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=61, nullable=true)
     */
    protected $path;

    /**
     * @ORM\Column(type="string", length=61, nullable=true)
     */
    protected $systemPath;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $url;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", fetch="EAGER")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $user;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $brouillon;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $tweetPostId;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $facebookEventId;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $tweetPostSystemId;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $fbPostId;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $fbPostSystemId;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Calendrier", mappedBy="event", cascade={"persist", "merge", "remove"}, fetch="EXTRA_LAZY")
     */
    protected $calendriers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Comment", mappedBy="event", cascade={"persist", "merge", "remove"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"createdAt": "DESC"})
     */
    protected $commentaires;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=31, nullable=true)
     */
    protected $facebookOwnerId;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $fbParticipations;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $fbInterets;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $participations;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $interets;

    /**
     * @var int
     *
     * @ORM\Column(type="string", length=256, nullable=true)
     */
    protected $source;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Place", cascade={"persist", "merge"}, fetch="EAGER")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"list_event"})
     * @Expose
     * @Assert\Valid
     */
    protected $place;

    /**
     * @var Reject|null
     */
    protected $reject;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    protected $archive;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="Vous devez indiquer le lieu de votre événement")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $placeName;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $placeStreet;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $placeCity;

    /**
     * @ORM\Column(type="string", length=7, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $placePostalCode;

    /**
     * @ORM\Column(type="string", length=127, nullable=true)
     */
    protected $placeExternalId;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $placeFacebookId;

    /**
     * @var Reject|null
     */
    protected $placeReject;

    /**
     * @var string|null
     */
    protected $placeCountryName;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     * @ORM\JoinColumn(nullable=true)
     *
     * @var Country|null
     */
    protected $placeCountry;

    public function __construct()
    {
        $this->dateDebut = new DateTime();
        $this->calendriers = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->brouillon = false;
        $this->archive = false;
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

    public function getFile()
    {
        return $this->file;
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
     * @return Event
     */
    public function setFile(File $image = null)
    {
        $this->file = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getSystemFile()
    {
        return $this->systemFile;
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
     * @return Event
     */
    public function setSystemFile(File $image = null)
    {
        $this->systemFile = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->updatedAt = new \DateTimeImmutable();
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

    public function getExternalUpdatedAt(): ?\DateTimeInterface
    {
        return $this->externalUpdatedAt;
    }

    public function setExternalUpdatedAt(?\DateTimeInterface $externalUpdatedAt): self
    {
        $this->externalUpdatedAt = $externalUpdatedAt;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
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

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getSystemPath(): ?string
    {
        return $this->systemPath;
    }

    public function setSystemPath(?string $systemPath): self
    {
        $this->systemPath = $systemPath;

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
            $calendrier->setEvent($this);
        }

        return $this;
    }

    public function removeCalendrier(Calendrier $calendrier): self
    {
        if ($this->calendriers->contains($calendrier)) {
            $this->calendriers->removeElement($calendrier);
            // set the owning side to null (unless already changed)
            if ($calendrier->getEvent() === $this) {
                $calendrier->setEvent(null);
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
}
