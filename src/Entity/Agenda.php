<?php

namespace App\Entity;

use App\Geolocalize\GeolocalizeInterface;
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
 * Agenda.
 *
 * @ORM\Table(name="Agenda", indexes={
 *   @ORM\Index(name="agenda_theme_manifestation_idx", columns={"theme_manifestation"}),
 *   @ORM\Index(name="agenda_type_manifestation_idx", columns={"type_manifestation"}),
 *   @ORM\Index(name="agenda_categorie_manifestation_idx", columns={"categorie_manifestation"}),
 *   @ORM\Index(name="agenda_search_idx", columns={"place_id", "date_fin", "date_debut"}),
 *   @ORM\Index(name="agenda_fb_participations", columns={"date_fin", "fb_participations", "fb_interets"})
 * })
 *
 * @ORM\Entity(repositoryClass="App\Repository\AgendaRepository")
 * @ORM\HasLifecycleCallbacks()
 * @ExclusionPolicy("all")
 * @Vich\Uploadable
 * @EventConstraint
 */
class Agenda implements GeolocalizeInterface
{
    const INDEX_FROM = '-6 months';

    const INDEX_TO = '+6 months';

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $id;

    /**
     * @Gedmo\Slug(fields={"nom"})
     * @ORM\Column(length=255, unique=true)
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255, nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de nommer votre événement !")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="descriptif", type="text", nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de décrire votre événement !")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $descriptif;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_modification", type="datetime", nullable=true)
     */
    protected $dateModification;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="fb_date_modification", type="datetime", nullable=true)
     */
    protected $fbDateModification;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_debut", type="date", nullable=true)
     * @Assert\NotBlank(message="Vous devez donner une date à votre événement")
     * @Groups({"list_event"})
     * @Expose
     * @Type("DateTime<'Y-m-d'>")
     */
    protected $dateDebut;

    /**
     * @var DateTime
     *
     * @ORM\Column(name="date_fin", type="date", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     * @Type("DateTime<'Y-m-d'>")
     */
    protected $dateFin;

    /**
     * @var string
     *
     * @ORM\Column(name="horaires", type="string", length=256, nullable=true)
     */
    protected $horaires;

    /**
     * @var string
     *
     * @ORM\Column(name="modification_derniere_minute", type="string", length=16, nullable=true)
     */
    protected $modificationDerniereMinute;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $latitude;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $longitude;

    /**
     * @var string
     *
     * @ORM\Column(name="adresse", type="string", length=255, nullable=true)
     */
    protected $adresse;

    /**
     * @var string
     *
     * @ORM\Column(name="type_manifestation", type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $typeManifestation;

    /**
     * @var string
     *
     * @ORM\Column(name="categorie_manifestation", type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $categorieManifestation;

    /**
     * @var string
     *
     * @ORM\Column(name="theme_manifestation", type="string", length=128, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $themeManifestation;

    /**
     * @var string
     *
     * @ORM\Column(name="reservation_telephone", type="string", length=255, nullable=true)
     */
    protected $reservationTelephone;

    /**
     * @var string
     *
     * @ORM\Column(name="reservation_email", type="string", length=255, nullable=true)
     * @Assert\Email()
     */
    protected $reservationEmail;

    /**
     * @var string
     *
     * @ORM\Column(name="reservation_internet", type="string", length=512, nullable=true)
     * @Assert\Url()
     */
    protected $reservationInternet;

    /**
     * @var string
     *
     * @ORM\Column(name="tarif", type="string", length=255, nullable=true)
     */
    protected $tarif;

    /**
     * @var string
     *
     * @ORM\Column(name="from_data", type="string", length=127, nullable=true)
     */
    protected $fromData;

    /**
     * @Vich\UploadableField(mapping="event_image", fileNameProperty="path")
     * @Assert\Valid()
     * @Assert\File(maxSize = "6M")
     * @Assert\Image()
     */
    protected $file;

    /**
     * @Vich\UploadableField(mapping="event_image", fileNameProperty="systemPath")
     * @Assert\Valid()
     * @Assert\File(maxSize = "6M")
     * @Assert\Image()
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
     * @ORM\Column(type="string", name="system_path", length=61, nullable=true)
     */
    protected $systemPath;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $url;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $user;

    /**
     * @var bool
     *
     * @ORM\Column(name="isBrouillon", type="boolean", nullable=true)
     */
    protected $isBrouillon;

    /**
     * @var int
     *
     * @ORM\Column(name="tweet_post_id", type="string", length=127, nullable=true)
     */
    protected $tweetPostId;

    /**
     * @var int
     *
     * @ORM\Column(name="facebook_event_id", type="string", length=127, nullable=true)
     */
    protected $facebookEventId;

    /**
     * @var int
     *
     * @ORM\Column(name="tweet_post_system_id", type="string", length=127, nullable=true)
     */
    protected $tweetPostSystemId;

    /**
     * @var int
     *
     * @ORM\Column(name="fb_post_id", type="string", length=127, nullable=true)
     */
    protected $fbPostId;

    /**
     * @var int
     *
     * @ORM\Column(name="fb_post_system_id", type="string", length=127, nullable=true)
     */
    protected $fbPostSystemId;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Calendrier", mappedBy="agenda", cascade={"persist", "merge", "remove"}, fetch="EXTRA_LAZY")
     */
    protected $calendriers;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Site", cascade={"persist", "merge"})
     * @ORM\JoinColumn(nullable=true)
     */
    protected $site;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Comment", mappedBy="agenda", cascade={"persist", "merge", "remove"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"dateCreation" = "DESC"})
     */
    protected $commentaires;

    /**
     * @var int
     *
     * @ORM\Column(name="facebook_owner_id", type="string", length=31, nullable=true)
     */
    protected $facebookOwnerId;

    /**
     * @var int
     *
     * @ORM\Column(name="fb_participations", type="integer", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $fbParticipations;

    /**
     * @var int
     *
     * @ORM\Column(name="fb_interets",type="integer", nullable=true)
     */
    protected $fbInterets;

    /**
     * @var int
     *
     * @ORM\Column(name="participations", type="integer", nullable=true)
     */
    protected $participations;

    /**
     * @var int
     *
     * @ORM\Column(name="interets", type="integer", nullable=true)
     */
    protected $interets;

    /**
     * @var int
     *
     * @ORM\Column(name="source", type="string", length=256, nullable=true)
     */
    protected $source;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Place", cascade={"persist", "merge"})
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"list_event"})
     * @Expose
     * @Assert\Valid()
     */
    protected $place;

    /**
     * @var Reject
     */
    protected $reject;

    /**
     * @var bool
     *
     * @ORM\Column(name="is_archive", type="boolean", nullable=true)
     */
    protected $isArchive;

    public function setReject(Reject $reject = null)
    {
        $this->reject = $reject;

        return $this;
    }

    public function getReject()
    {
        return $this->reject;
    }

    public function isIndexable()
    {
        $from = new DateTime();
        $from->modify(self::INDEX_FROM);

        $to = new DateTime();
        $to->modify(self::INDEX_TO);

        return $this->dateFin >= $from && $this->dateFin <= $to;
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
     * @return Agenda
     */
    public function setFile(File $image = null)
    {
        $this->file = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->dateModification = new DateTime();
        }

        return $this;
    }

    /**
     * @return File
     */
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
     * @return Agenda
     */
    public function setSystemFile(File $image = null)
    {
        $this->systemFile = $image;

        if ($image) {
            // It is required that at least one field changes if you are using doctrine
            // otherwise the event listeners won't be called and the file is lost
            $this->dateModification = new DateTime();
        }

        return $this;
    }

    /**
     * @return File
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function majDateFin()
    {
        if (null === $this->getDateFin()) {
            $this->setDateFin($this->getDateDebut());
        }
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function preDateModification()
    {
        $this->dateModification = new DateTime();
    }

    public function __construct()
    {
        $this->setDateDebut(new DateTime());
        $this->place        = new Place();
        $this->calendriers  = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->isArchive    = false;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    public function getDistinctTags()
    {
        $tags = $this->getCategorieManifestation() . ',' . $this->getTypeManifestation() . ',' . $this->getThemeManifestation();
        return \array_unique(\array_map('trim', \array_map('ucfirst', \array_filter(\preg_split('#[,/]#', $tags)))));
    }

    public function __toString()
    {
        return '#' . $this->id ?: '?';
    }

    /**
     * Set latitude.
     *
     * @param float $latitude
     *
     * @return Agenda
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude.
     *
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude.
     *
     * @param float $longitude
     *
     * @return Agenda
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude.
     *
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
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
     * Set slug.
     *
     * @param string $slug
     *
     * @return Agenda
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
     * Set nom.
     *
     * @param string $nom
     *
     * @return Agenda
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom.
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set descriptif.
     *
     * @param string $descriptif
     *
     * @return Agenda
     */
    public function setDescriptif($descriptif)
    {
        $this->descriptif = $descriptif;

        return $this;
    }

    /**
     * Get descriptif.
     *
     * @return string
     */
    public function getDescriptif()
    {
        return $this->descriptif;
    }

    /**
     * Set dateModification.
     *
     * @param DateTime $dateModification
     *
     * @return Agenda
     */
    public function setDateModification($dateModification)
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    /**
     * Get dateModification.
     *
     * @return DateTime
     */
    public function getDateModification()
    {
        return $this->dateModification;
    }

    /**
     * Set fbDateModification.
     *
     * @param DateTime $fbDateModification
     *
     * @return Agenda
     */
    public function setFbDateModification($fbDateModification)
    {
        $this->fbDateModification = $fbDateModification;

        return $this;
    }

    /**
     * Get fbDateModification.
     *
     * @return DateTime
     */
    public function getFbDateModification()
    {
        return $this->fbDateModification;
    }

    /**
     * Set dateDebut.
     *
     * @param DateTime $dateDebut
     *
     * @return Agenda
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    /**
     * Get dateDebut.
     *
     * @return DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * Set dateFin.
     *
     * @param DateTime $dateFin
     *
     * @return Agenda
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Get dateFin.
     *
     * @return DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * Set horaires.
     *
     * @param string $horaires
     *
     * @return Agenda
     */
    public function setHoraires($horaires)
    {
        $this->horaires = $horaires;

        return $this;
    }

    /**
     * Get horaires.
     *
     * @return string
     */
    public function getHoraires()
    {
        return $this->horaires;
    }

    /**
     * Set modificationDerniereMinute.
     *
     * @param string $modificationDerniereMinute
     *
     * @return Agenda
     */
    public function setModificationDerniereMinute($modificationDerniereMinute)
    {
        $this->modificationDerniereMinute = $modificationDerniereMinute;

        return $this;
    }

    /**
     * Get modificationDerniereMinute.
     *
     * @return string
     */
    public function getModificationDerniereMinute()
    {
        return $this->modificationDerniereMinute;
    }

    /**
     * Set adresse.
     *
     * @param string $adresse
     *
     * @return Agenda
     */
    public function setAdresse($adresse)
    {
        $this->adresse = $adresse;

        return $this;
    }

    /**
     * Get adresse.
     *
     * @return string
     */
    public function getAdresse()
    {
        return $this->adresse;
    }

    /**
     * Set typeManifestation.
     *
     * @param string $typeManifestation
     *
     * @return Agenda
     */
    public function setTypeManifestation($typeManifestation)
    {
        $this->typeManifestation = $typeManifestation;

        return $this;
    }

    /**
     * Get typeManifestation.
     *
     * @return string
     */
    public function getTypeManifestation()
    {
        return $this->typeManifestation;
    }

    /**
     * Set categorieManifestation.
     *
     * @param string $categorieManifestation
     *
     * @return Agenda
     */
    public function setCategorieManifestation($categorieManifestation)
    {
        $this->categorieManifestation = $categorieManifestation;

        return $this;
    }

    /**
     * Get categorieManifestation.
     *
     * @return string
     */
    public function getCategorieManifestation()
    {
        return $this->categorieManifestation;
    }

    /**
     * Set themeManifestation.
     *
     * @param string $themeManifestation
     *
     * @return Agenda
     */
    public function setThemeManifestation($themeManifestation)
    {
        $this->themeManifestation = $themeManifestation;

        return $this;
    }

    /**
     * Get themeManifestation.
     *
     * @return string
     */
    public function getThemeManifestation()
    {
        return $this->themeManifestation;
    }

    /**
     * Set reservationTelephone.
     *
     * @param string $reservationTelephone
     *
     * @return Agenda
     */
    public function setReservationTelephone($reservationTelephone)
    {
        $this->reservationTelephone = $reservationTelephone;

        return $this;
    }

    /**
     * Get reservationTelephone.
     *
     * @return string
     */
    public function getReservationTelephone()
    {
        return $this->reservationTelephone;
    }

    /**
     * Set reservationEmail.
     *
     * @param string $reservationEmail
     *
     * @return Agenda
     */
    public function setReservationEmail($reservationEmail)
    {
        $this->reservationEmail = $reservationEmail;

        return $this;
    }

    /**
     * Get reservationEmail.
     *
     * @return string
     */
    public function getReservationEmail()
    {
        return $this->reservationEmail;
    }

    /**
     * Set reservationInternet.
     *
     * @param string $reservationInternet
     *
     * @return Agenda
     */
    public function setReservationInternet($reservationInternet)
    {
        $this->reservationInternet = $reservationInternet;

        return $this;
    }

    /**
     * Get reservationInternet.
     *
     * @return string
     */
    public function getReservationInternet()
    {
        return $this->reservationInternet;
    }

    /**
     * Set tarif.
     *
     * @param string $tarif
     *
     * @return Agenda
     */
    public function setTarif($tarif)
    {
        $this->tarif = $tarif;

        return $this;
    }

    /**
     * Get tarif.
     *
     * @return string
     */
    public function getTarif()
    {
        return $this->tarif;
    }

    /**
     * Set fromData.
     *
     * @param string $fromData
     *
     * @return Agenda
     */
    public function setFromData($fromData)
    {
        $this->fromData = $fromData;

        return $this;
    }

    /**
     * Get fromData.
     *
     * @return string
     */
    public function getFromData()
    {
        return $this->fromData;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return Agenda
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set path.
     *
     * @param string $path
     *
     * @return Agenda
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
     * @return Agenda
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
     * Set url.
     *
     * @param string $url
     *
     * @return Agenda
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set isBrouillon.
     *
     * @param bool $isBrouillon
     *
     * @return Agenda
     */
    public function setIsBrouillon($isBrouillon)
    {
        $this->isBrouillon = $isBrouillon;

        return $this;
    }

    /**
     * Get isBrouillon.
     *
     * @return bool
     */
    public function getIsBrouillon()
    {
        return $this->isBrouillon;
    }

    /**
     * Set tweetPostId.
     *
     * @param string $tweetPostId
     *
     * @return Agenda
     */
    public function setTweetPostId($tweetPostId)
    {
        $this->tweetPostId = $tweetPostId;

        return $this;
    }

    /**
     * Get tweetPostId.
     *
     * @return string
     */
    public function getTweetPostId()
    {
        return $this->tweetPostId;
    }

    /**
     * Set facebookEventId.
     *
     * @param string $facebookEventId
     *
     * @return Agenda
     */
    public function setFacebookEventId($facebookEventId)
    {
        $this->facebookEventId = $facebookEventId;

        return $this;
    }

    /**
     * Get facebookEventId.
     *
     * @return string
     */
    public function getFacebookEventId()
    {
        return $this->facebookEventId;
    }

    /**
     * Set tweetPostSystemId.
     *
     * @param string $tweetPostSystemId
     *
     * @return Agenda
     */
    public function setTweetPostSystemId($tweetPostSystemId)
    {
        $this->tweetPostSystemId = $tweetPostSystemId;

        return $this;
    }

    /**
     * Get tweetPostSystemId.
     *
     * @return string
     */
    public function getTweetPostSystemId()
    {
        return $this->tweetPostSystemId;
    }

    /**
     * Set fbPostId.
     *
     * @param string $fbPostId
     *
     * @return Agenda
     */
    public function setFbPostId($fbPostId)
    {
        $this->fbPostId = $fbPostId;

        return $this;
    }

    /**
     * Get fbPostId.
     *
     * @return string
     */
    public function getFbPostId()
    {
        return $this->fbPostId;
    }

    /**
     * Set fbPostSystemId.
     *
     * @param string $fbPostSystemId
     *
     * @return Agenda
     */
    public function setFbPostSystemId($fbPostSystemId)
    {
        $this->fbPostSystemId = $fbPostSystemId;

        return $this;
    }

    /**
     * Get fbPostSystemId.
     *
     * @return string
     */
    public function getFbPostSystemId()
    {
        return $this->fbPostSystemId;
    }

    /**
     * Set facebookOwnerId.
     *
     * @param string $facebookOwnerId
     *
     * @return Agenda
     */
    public function setFacebookOwnerId($facebookOwnerId)
    {
        $this->facebookOwnerId = $facebookOwnerId;

        return $this;
    }

    /**
     * Get facebookOwnerId.
     *
     * @return string
     */
    public function getFacebookOwnerId()
    {
        return $this->facebookOwnerId;
    }

    /**
     * Set fbParticipations.
     *
     * @param int $fbParticipations
     *
     * @return Agenda
     */
    public function setFbParticipations($fbParticipations)
    {
        $this->fbParticipations = $fbParticipations;

        return $this;
    }

    /**
     * Get fbParticipations.
     *
     * @return int
     */
    public function getFbParticipations()
    {
        return $this->fbParticipations;
    }

    /**
     * Set fbInterets.
     *
     * @param int $fbInterets
     *
     * @return Agenda
     */
    public function setFbInterets($fbInterets)
    {
        $this->fbInterets = $fbInterets;

        return $this;
    }

    /**
     * Get fbInterets.
     *
     * @return int
     */
    public function getFbInterets()
    {
        return $this->fbInterets;
    }

    /**
     * Set participations.
     *
     * @param int $participations
     *
     * @return Agenda
     */
    public function setParticipations($participations)
    {
        $this->participations = $participations;

        return $this;
    }

    /**
     * Get participations.
     *
     * @return int
     */
    public function getParticipations()
    {
        return $this->participations;
    }

    /**
     * Set interets.
     *
     * @param int $interets
     *
     * @return Agenda
     */
    public function setInterets($interets)
    {
        $this->interets = $interets;

        return $this;
    }

    /**
     * Get interets.
     *
     * @return int
     */
    public function getInterets()
    {
        return $this->interets;
    }

    /**
     * Set source.
     *
     * @param string $source
     *
     * @return Agenda
     */
    public function setSource($source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * Get source.
     *
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set isArchive.
     *
     * @param bool $isArchive
     *
     * @return Agenda
     */
    public function setIsArchive($isArchive)
    {
        $this->isArchive = $isArchive;

        return $this;
    }

    /**
     * Get isArchive.
     *
     * @return bool
     */
    public function getIsArchive()
    {
        return $this->isArchive;
    }

    /**
     * Set user.
     *
     * @param User $user
     *
     * @return Agenda
     */
    public function setUser(User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user.
     *
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add calendrier.
     *
     * @param Calendrier $calendrier
     *
     * @return Agenda
     */
    public function addCalendrier(Calendrier $calendrier)
    {
        $this->calendriers[] = $calendrier;

        return $this;
    }

    /**
     * Remove calendrier.
     *
     * @param Calendrier $calendrier
     */
    public function removeCalendrier(Calendrier $calendrier)
    {
        $this->calendriers->removeElement($calendrier);
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
     * Set site.
     *
     * @param Site $site
     *
     * @return Agenda
     */
    public function setSite(Site $site)
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Get site.
     *
     * @return Site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Add commentaire.
     *
     * @param Comment $commentaire
     *
     * @return Agenda
     */
    public function addCommentaire(Comment $commentaire)
    {
        $this->commentaires[] = $commentaire;

        return $this;
    }

    /**
     * Remove commentaire.
     *
     * @param Comment $commentaire
     */
    public function removeCommentaire(Comment $commentaire)
    {
        $this->commentaires->removeElement($commentaire);
    }

    /**
     * Get commentaires.
     *
     * @return Collection
     */
    public function getCommentaires()
    {
        return $this->commentaires;
    }

    /**
     * Set place.
     *
     * @param Place $place
     *
     * @return Agenda
     */
    public function setPlace(Place $place = null)
    {
        $this->place = $place;

        return $this;
    }

    /**
     * Get place.
     *
     * @return Place
     */
    public function getPlace()
    {
        return $this->place;
    }
}
