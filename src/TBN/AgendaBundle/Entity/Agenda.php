<?php

namespace TBN\AgendaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use \Doctrine\Common\Collections\ArrayCollection;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Type;

use TBN\AgendaBundle\Entity\Place;
use TBN\AgendaBundle\Entity\Agenda;

/**
 * Agenda
 *
 * @ORM\Table(name="Agenda", indexes={
 *   @ORM\Index(name="agenda_slug_idx", columns={"slug"}),
 *   @ORM\Index(name="agenda_nom_idx", columns={"nom"}),
 *   @ORM\Index(name="agenda_theme_manifestation_idx", columns={"theme_manifestation"}),
 *   @ORM\Index(name="agenda_type_manifestation_idx", columns={"type_manifestation"}),
 *   @ORM\Index(name="agenda_date_debut_idx", columns={"date_debut"}),
 *   @ORM\Index(name="agenda_fb_idx", columns={"facebook_event_id"}),
 *   @ORM\Index(name="agenda_search_idx", columns={"site_id", "date_fin", "date_debut"}),
 *   @ORM\Index(name="agenda_search2_idx", columns={"site_id", "date_debut"})
 * })
 * 
 * @ORM\Entity(repositoryClass="TBN\AgendaBundle\Repository\AgendaRepository")
 * @ORM\HasLifecycleCallbacks()
 * @ExclusionPolicy("all")
 */
class Agenda
{

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Expose
     */
    protected $id;

    /**
    * @Gedmo\Slug(fields={"nom"})
    * @ORM\Column(length=128, unique=true)
    */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255, nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de nommer votre événement !")
     * @Expose
     */
    protected $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="descriptif", type="text", nullable=true)
     * @Assert\NotBlank(message="N'oubliez pas de décrire votre événement !")
     * @Expose
     */
    protected $descriptif;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_modification", type="datetime", nullable=true)
     */
    protected $dateModification;
    
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="fb_date_modification", type="datetime", nullable=true)
     */
    protected $fbDateModification;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_debut", type="date", nullable=true)
     * @Assert\NotBlank(message="Vous devez donner une date à votre événement")
     * @Expose
     * @Type("DateTime<'Y-m-d'>")
     */
    protected $dateDebut;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_fin", type="date", nullable=true)
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
     * @var string
     *
     * @ORM\Column(name="ville", type="string", length=255, nullable=true)
     * 
     */
    protected $ville;

    /**
     * @var integer
     *
     * @ORM\Column(name="rue", type="string", length=255, nullable=true)
     */
    protected $rue;

    /**
     * @var string
     *
     * @ORM\Column(name="code_postal", type="string", length=15, nullable=true)
     */
    protected $codePostal;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="float", nullable=true)
     * @Expose
     */
    protected $latitude;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="float", nullable=true)
     */
    protected $longitude;

    /**
     * @var string
     *
     * @ORM\Column(name="commune", type="string", length=255, nullable=true)
     */
    protected $commune;

    /**
     * @var string
     *
     * @ORM\Column(name="lieu_nom", type="string", length=255, nullable=true)
     * 
     */
    protected $lieuNom;

    /**
     * @var string
     *
     * @ORM\Column(name="adresse", type="string", length=255, nullable=true)
     * @Expose
     */
    protected $adresse;

    /**
     * @var string
     *
     * @ORM\Column(name="type_manifestation", type="string", length=128, nullable=true)
     * @Expose
     */
    protected $typeManifestation;

    /**
     * @var string
     *
     * @ORM\Column(name="categorie_manifestation", type="string", length=128, nullable=true)
     * @Expose
     */
    protected $categorieManifestation;

    /**
     * @var string
     *
     * @ORM\Column(name="theme_manifestation", type="string", length=128, nullable=true)
     * @Expose
     */
    protected $themeManifestation;

    /**
     * @var string
     *
     * @ORM\Column(name="station_metro_tram", type="string", length=255, nullable=true)
     */
    protected $stationMetroTram;

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
     * @ORM\Column(name="tranche_age", type="string", length=128, nullable=true)
     */
    protected $trancheAge;

    /**
     * @var string
     *
     * @ORM\Column(name="from_data", type="string", length=127, nullable=true)
     */
    protected $fromData;

    /**
     * @Assert\File(maxSize="6000000")
     * @Assert\Valid()
     */
    protected $file;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $name;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $path;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $url;


    /**
     * @ORM\ManyToOne(targetEntity="TBN\UserBundle\Entity\User", inversedBy="evenements")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $user;

    /**
     *
     * @var boolean $isEnabled
     *
     * @ORM\Column(name="isBrouillon", type="boolean", nullable=true)
     */
    protected $isBrouillon;

    /**
     *
     * @var boolean $isMigrated
     *
     * @ORM\Column(name="isMigrated", type="boolean", nullable=true)
     */
    protected $isMigrated;

    /**
     * @var integer
     *
     * @ORM\Column(name="tweet_post_id", type="string", length=256, nullable=true)
     */
    protected $tweetPostId;

    /**
     * @var integer
     *
     * @ORM\Column(name="facebook_event_id", type="string", length=256, nullable=true)
     */
    protected $facebookEventId;

    /**
     * @var integer
     *
     * @ORM\Column(name="tweet_post_system_id", type="string", length=256, nullable=true)
     */
    protected $tweetPostSystemId;

    /**
     * @var integer
     *
     * @ORM\Column(name="fb_post_id", type="string", length=256, nullable=true)
     */
    protected $fbPostId;

    /**
     * @var integer
     *
     * @ORM\Column(name="fb_post_system_id", type="string", length=256, nullable=true)
     */
    protected $fbPostSystemId;

    /**
     * @var integer
     *
     * @ORM\Column(name="google_post_id", type="string", length=256, nullable=true)
     */
    protected $googlePostId;

    /**
     * @var integer
     *
     * @ORM\Column(name="google_post_system_id", type="string", length=256, nullable=true)
     */
    protected $googleSystemPostId;

    /**
    * @ORM\OneToMany(targetEntity="TBN\AgendaBundle\Entity\Calendrier", mappedBy="agenda", cascade={"remove"}, fetch="EXTRA_LAZY")
    */
    protected $calendriers;

    /**
    * @ORM\ManyToOne(targetEntity="TBN\MainBundle\Entity\Site", cascade={"persist", "merge"})
    * @ORM\JoinColumn(nullable=false)
    * @Expose
    */
    protected $site;

    /**
     * @ORM\OneToMany(targetEntity="TBN\CommentBundle\Entity\Comment", mappedBy="agenda", cascade={"remove"}, fetch="EXTRA_LAZY")
     * @ORM\OrderBy({"dateCreation" = "DESC"})
     */
    protected $commentaires;

    /**
     * @var integer
     *
     * @ORM\Column(name="facebook_owner_id", type="string", length=256, nullable=true)
     */
    protected $facebookOwnerId;

    /**
     * @var integer
     *
     * @ORM\Column(name="fb_participations", type="integer", nullable=true)
     * @Expose
     */
    protected $fbParticipations;

    /**
     * @var integer
     *
     * @ORM\Column(name="fb_interets",type="integer", nullable=true)
     */
    protected $fbInterets;


    /**
     * @var integer
     *
     * @ORM\Column(name="participations", type="integer", nullable=true)
     */
    protected $participations;

    /**
     * @var integer
     *
     * @ORM\Column(name="interets", type="integer", nullable=true)
     */
    protected $interets;

    /**
     * @var boolean
     */
    protected $isTrustedLocation;

    /**
     * @var integer
     *
     * @ORM\Column(name="source", type="string", length=256, nullable=true)
     */
    protected $source;
    
    /**
     * @ORM\ManyToOne(targetEntity="TBN\AgendaBundle\Entity\Place", cascade={"persist", "merge"})
     * @ORM\JoinColumn(nullable=true)
     * @Expose
     * @Assert\Valid()
     */
    protected $place;

    protected $rejectReasons;

    public function getAbsolutePath()
    {
        return null === $this->path ? null : $this->getUploadRootDir().'/'.$this->path;
    }

    public function getWebPath()
    {
        return null === $this->path ? null : $this->getUploadDir().'/'.$this->path;
    }

    public function getUploadRootDir()
    {
        // le chemin absolu du répertoire où les documents uploadés doivent être sauvegardés
        return __DIR__.'/../../../../web/'.$this->getUploadDir();
    }

    protected function getUploadDir()
    {
        // on se débarrasse de « __DIR__ » afin de ne pas avoir de problème lorsqu'on affiche
        // le document/image dans la vue.
        return 'uploads/documents';
    }

    /**
     * Sets file.
     *
     * @param UploadedFile $file
     */
    public function setFile(UploadedFile $file = null)
    {
        $this->file = $file;
        // check if we have an old image path
        if (isset($this->path)) {
            // store the old name to delete after the update
            $this->temp = $this->path;
            $this->path = null;
        } else {
            $this->path = 'initial';
        }
    }

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
    public function preUpload()
    {
        if (null !== $this->getFile()) {
            // do whatever you want to generate a unique name
            $filename = sha1(uniqid(mt_rand(), true));
            $this->path = $filename.'.'.$this->getFile()->guessExtension();
        }
    }

    /**
     * @ORM\PostPersist
     * @ORM\PostUpdate
     */
    public function upload()
    {
        if (null === $this->getFile()) {
            return;
        }

        // if there is an error when moving the file, an exception will
        // be automatically thrown by move(). This will properly prevent
        // the entity from being persisted to the database on error
        $this->getFile()->move($this->getUploadRootDir(), $this->path);

        // check if we have an old image
        if (isset($this->temp)) {
            // delete the old image
            unlink($this->getUploadRootDir().'/'.$this->temp);
            // clear the temp image path
            $this->temp = null;
        }
        $this->file = null;
    }

    /**
     * @ORM\PostRemove
     */
    public function removeUpload()
    {
        if (($file = $this->getAbsolutePath())) {
            unlink($file);
        }
    }

    /**
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function preDateModification()
    {
        $this->dateModification = new \DateTime;
    }

    /**
     * @Assert\IsTrue(message = "La date de fin de l'événement doit être supérieure à la date de début")
     */
    public function isDatesValid()
    {
        if($this->dateFin === null)
        {
            return true;
        }
        
        return $this->dateFin >= $this->dateDebut;
    }


    public function __construct()
    {
        $this->rejectReasons = [];
        $this->dateDebut = new \DateTime;
        $this->place = new Place;
        $this->calendriers = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
    }
    
    public function getDistinctTags() {
        $tags = $this->getCategorieManifestation().','.$this->getTypeManifestation().','.$this->getThemeManifestation();
        return array_unique(array_map('trim', array_map('ucfirst', array_filter(explode(',', $tags)))));
    }

    public function addRejectReason($reason) {
        $this->rejectReasons[] = $reason;

        return $this;
    }

    public function getRejectReaons() {
        return $this->rejectReasons;
    }
    
    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set slug
     *
     * @param string $slug
     * @return Agenda
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * Get slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set nom
     *
     * @param string $nom
     * @return Agenda
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set descriptif
     *
     * @param string $descriptif
     * @return Agenda
     */
    public function setDescriptif($descriptif)
    {
        $this->descriptif = $descriptif;

        return $this;
    }

    /**
     * Get descriptif
     *
     * @return string
     */
    public function getDescriptif()
    {
        return $this->descriptif;
    }

    /**
     * Set dateCreation
     *
     * @param \DateTime $dateCreation
     * @return Agenda
     */
    public function setDateCreation($dateCreation)
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    /**
     * Get dateCreation
     *
     * @return \DateTime
     */
    public function getDateCreation()
    {
        return $this->dateCreation;
    }

    /**
     * Set dateModification
     *
     * @param \DateTime $dateModification
     * @return Agenda
     */
    public function setDateModification($dateModification)
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    /**
     * Get dateModification
     *
     * @return \DateTime
     */
    public function getDateModification()
    {
        return $this->dateModification;
    }

    /**
     * Set dateDebut
     *
     * @param \DateTime $dateDebut
     * @return Agenda
     */
    public function setDateDebut($dateDebut)
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    /**
     * Get dateDebut
     *
     * @return \DateTime
     */
    public function getDateDebut()
    {
        return $this->dateDebut;
    }

    /**
     * Set dateFin
     *
     * @param \DateTime $dateFin
     * @return Agenda
     */
    public function setDateFin($dateFin)
    {
        $this->dateFin = $dateFin;

        return $this;
    }

    /**
     * Get dateFin
     *
     * @return \DateTime
     */
    public function getDateFin()
    {
        return $this->dateFin;
    }

    /**
     * Set horaires
     *
     * @param string $horaires
     * @return Agenda
     */
    public function setHoraires($horaires)
    {
        $this->horaires = $horaires;

        return $this;
    }

    /**
     * Get horaires
     *
     * @return string
     */
    public function getHoraires()
    {
        return $this->horaires;
    }

    /**
     * Set modificationDerniereMinute
     *
     * @param string $modificationDerniereMinute
     * @return Agenda
     */
    public function setModificationDerniereMinute($modificationDerniereMinute)
    {
        $this->modificationDerniereMinute = $modificationDerniereMinute;

        return $this;
    }

    /**
     * Get modificationDerniereMinute
     *
     * @return string
     */
    public function getModificationDerniereMinute()
    {
        return $this->modificationDerniereMinute;
    }

    /**
     * Set ville
     *
     * @param string $ville
     * @return Agenda
     */
    public function setVille($ville)
    {
        $this->ville = $ville;

        return $this;
    }

    /**
     * Get ville
     *
     * @return string
     */
    public function getVille()
    {
        return $this->ville;
    }

    /**
     * Set rue
     *
     * @param string $rue
     * @return Agenda
     */
    public function setRue($rue)
    {
        $this->rue = $rue;

        return $this;
    }

    /**
     * Get rue
     *
     * @return string
     */
    public function getRue()
    {
        return $this->rue;
    }

    /**
     * Set codePostal
     *
     * @param string $codePostal
     * @return Agenda
     */
    public function setCodePostal($codePostal)
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    /**
     * Get codePostal
     *
     * @return string
     */
    public function getCodePostal()
    {
        return $this->codePostal;
    }

    /**
     * Set latitude
     *
     * @param float $latitude
     * @return Agenda
     */
    public function setLatitude($latitude)
    {
        $this->latitude = $latitude;

        return $this;
    }

    /**
     * Get latitude
     *
     * @return float
     */
    public function getLatitude()
    {
        return $this->latitude;
    }

    /**
     * Set longitude
     *
     * @param float $longitude
     * @return Agenda
     */
    public function setLongitude($longitude)
    {
        $this->longitude = $longitude;

        return $this;
    }

    /**
     * Get longitude
     *
     * @return float
     */
    public function getLongitude()
    {
        return $this->longitude;
    }

    /**
     * Set commune
     *
     * @param string $commune
     * @return Agenda
     */
    public function setCommune($commune)
    {
        $this->commune = $commune;

        return $this;
    }

    /**
     * Get commune
     *
     * @return string
     */
    public function getCommune()
    {
        return $this->commune;
    }

    /**
     * Set lieuNom
     *
     * @param string $lieuNom
     * @return Agenda
     */
    public function setLieuNom($lieuNom)
    {
        $this->lieuNom = $lieuNom;

        return $this;
    }

    /**
     * Get lieuNom
     *
     * @return string
     */
    public function getLieuNom()
    {
        return $this->lieuNom;
    }

    /**
     * Set typeManifestation
     *
     * @param string $typeManifestation
     * @return Agenda
     */
    public function setTypeManifestation($typeManifestation)
    {
        $this->typeManifestation = $typeManifestation;

        return $this;
    }

    /**
     * Get typeManifestation
     *
     * @return string
     */
    public function getTypeManifestation()
    {
        return $this->typeManifestation;
    }

    /**
     * Set categorieManifestation
     *
     * @param string $categorieManifestation
     * @return Agenda
     */
    public function setCategorieManifestation($categorieManifestation)
    {
        $this->categorieManifestation = $categorieManifestation;

        return $this;
    }

    /**
     * Get categorieManifestation
     *
     * @return string
     */
    public function getCategorieManifestation()
    {
        return $this->categorieManifestation;
    }

    /**
     * Set themeManifestation
     *
     * @param string $themeManifestation
     * @return Agenda
     */
    public function setThemeManifestation($themeManifestation)
    {
        $this->themeManifestation = $themeManifestation;

        return $this;
    }

    /**
     * Get themeManifestation
     *
     * @return string
     */
    public function getThemeManifestation()
    {
        return $this->themeManifestation;
    }

    /**
     * Set stationMetroTram
     *
     * @param string $stationMetroTram
     * @return Agenda
     */
    public function setStationMetroTram($stationMetroTram)
    {
        $this->stationMetroTram = $stationMetroTram;

        return $this;
    }

    /**
     * Get stationMetroTram
     *
     * @return string
     */
    public function getStationMetroTram()
    {
        return $this->stationMetroTram;
    }

    /**
     * Set reservationTelephone
     *
     * @param string $reservationTelephone
     * @return Agenda
     */
    public function setReservationTelephone($reservationTelephone)
    {
        $this->reservationTelephone = $reservationTelephone;

        return $this;
    }

    /**
     * Get reservationTelephone
     *
     * @return string
     */
    public function getReservationTelephone()
    {
        return $this->reservationTelephone;
    }

    /**
     * Set reservationEmail
     *
     * @param string $reservationEmail
     * @return Agenda
     */
    public function setReservationEmail($reservationEmail)
    {
        $this->reservationEmail = $reservationEmail;

        return $this;
    }

    /**
     * Get reservationEmail
     *
     * @return string
     */
    public function getReservationEmail()
    {
        return $this->reservationEmail;
    }

    /**
     * Set reservationInternet
     *
     * @param string $reservationInternet
     * @return Agenda
     */
    public function setReservationInternet($reservationInternet)
    {
        $this->reservationInternet = $reservationInternet;

        return $this;
    }

    /**
     * Get reservationInternet
     *
     * @return string
     */
    public function getReservationInternet()
    {
        return $this->reservationInternet;
    }

    /**
     * Set manifestationGratuite
     *
     * @param string $manifestationGratuite
     * @return Agenda
     */
    public function setManifestationGratuite($manifestationGratuite)
    {
        $this->manifestationGratuite = $manifestationGratuite;

        return $this;
    }

    /**
     * Get manifestationGratuite
     *
     * @return string
     */
    public function getManifestationGratuite()
    {
        return $this->manifestationGratuite;
    }

    /**
     * Set tarif
     *
     * @param string $tarif
     * @return Agenda
     */
    public function setTarif($tarif)
    {
        $this->tarif = $tarif;

        return $this;
    }

    /**
     * Get tarif
     *
     * @return string
     */
    public function getTarif()
    {
        return $this->tarif;
    }

    /**
     * Set trancheAge
     *
     * @param string $trancheAge
     * @return Agenda
     */
    public function setTrancheAge($trancheAge)
    {
        $this->trancheAge = $trancheAge;

        return $this;
    }

    /**
     * Get trancheAge
     *
     * @return string
     */
    public function getTrancheAge()
    {
        return $this->trancheAge;
    }

    /**
     * Set fromData
     *
     * @param string $fromData
     * @return Agenda
     */
    public function setFromData($fromData)
    {
        $this->fromData = $fromData;

        return $this;
    }

    /**
     * Get fromData
     *
     * @return string
     */
    public function getFromData()
    {
        return $this->fromData;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return Agenda
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set path
     *
     * @param string $path
     * @return Agenda
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Get path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return Agenda
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set isBrouillon
     *
     * @param boolean $isBrouillon
     * @return Agenda
     */
    public function setBrouillon($isBrouillon)
    {
        $this->isBrouillon = $isBrouillon;

        return $this;
    }

    /**
     * Get isBrouillon
     *
     * @return boolean
     */
    public function isBrouillon()
    {
        return $this->isBrouillon;
    }

    /**
     * Set tweetPostId
     *
     * @param string $tweetPostId
     * @return Agenda
     */
    public function setTweetPostId($tweetPostId)
    {
        $this->tweetPostId = $tweetPostId;

        return $this;
    }

    /**
     * Get tweetPostId
     *
     * @return string
     */
    public function getTweetPostId()
    {
        return $this->tweetPostId;
    }

    /**
     * Set facebookEventId
     *
     * @param string $facebookEventId
     * @return Agenda
     */
    public function setFacebookEventId($facebookEventId)
    {
        $this->facebookEventId = $facebookEventId;

        return $this;
    }

    /**
     * Get facebookEventId
     *
     * @return string
     */
    public function getFacebookEventId()
    {
        return $this->facebookEventId;
    }

    /**
     * Set tweetPostSystemId
     *
     * @param string $tweetPostSystemId
     * @return Agenda
     */
    public function setTweetPostSystemId($tweetPostSystemId)
    {
        $this->tweetPostSystemId = $tweetPostSystemId;

        return $this;
    }

    /**
     * Get tweetPostSystemId
     *
     * @return string
     */
    public function getTweetPostSystemId()
    {
        return $this->tweetPostSystemId;
    }

    /**
     * Set fbPostId
     *
     * @param string $fbPostId
     * @return Agenda
     */
    public function setFbPostId($fbPostId)
    {
        $this->fbPostId = $fbPostId;

        return $this;
    }

    /**
     * Get fbPostId
     *
     * @return string
     */
    public function getFbPostId()
    {
        return $this->fbPostId;
    }

    /**
     * Set fbPostSystemId
     *
     * @param string $fbPostSystemId
     * @return Agenda
     */
    public function setFbPostSystemId($fbPostSystemId)
    {
        $this->fbPostSystemId = $fbPostSystemId;

        return $this;
    }

    /**
     * Get fbPostSystemId
     *
     * @return string
     */
    public function getFbPostSystemId()
    {
        return $this->fbPostSystemId;
    }

    /**
     * Set googlePostId
     *
     * @param string $googlePostId
     * @return Agenda
     */
    public function setGooglePostId($googlePostId)
    {
        $this->googlePostId = $googlePostId;

        return $this;
    }

    /**
     * Get googlePostId
     *
     * @return string
     */
    public function getGooglePostId()
    {
        return $this->googlePostId;
    }

    /**
     * Set googleSystemPostId
     *
     * @param string $googleSystemPostId
     * @return Agenda
     */
    public function setGoogleSystemPostId($googleSystemPostId)
    {
        $this->googleSystemPostId = $googleSystemPostId;

        return $this;
    }

    /**
     * Get googleSystemPostId
     *
     * @return string
     */
    public function getGoogleSystemPostId()
    {
        return $this->googleSystemPostId;
    }

    /**
     * Set user
     *
     * @param \TBN\UserBundle\Entity\User $user
     * @return Agenda
     */
    public function setUser(\TBN\UserBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \TBN\UserBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Add calendriers
     *
     * @param \TBN\AgendaBundle\Entity\Calendrier $calendriers
     * @return Agenda
     */
    public function addCalendrier(\TBN\AgendaBundle\Entity\Calendrier $calendriers)
    {
        $this->calendriers[] = $calendriers;

        return $this;
    }

    /**
     * Remove calendriers
     *
     * @param \TBN\AgendaBundle\Entity\Calendrier $calendriers
     */
    public function removeCalendrier(\TBN\AgendaBundle\Entity\Calendrier $calendriers)
    {
        $this->calendriers->removeElement($calendriers);
    }

    /**
     * Get calendriers
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCalendriers()
    {
        return $this->calendriers;
    }

    /**
     * Set site
     *
     * @param \TBN\MainBundle\Entity\Site $site
     * @return Agenda
     */
    public function setSite(\TBN\MainBundle\Entity\Site $site)
    {
        $this->site = $site;

        return $this;
    }

    /**
     * Get site
     *
     * @return \TBN\MainBundle\Entity\Site
     */
    public function getSite()
    {
        return $this->site;
    }

    /**
     * Add commentaires
     *
     * @param \TBN\CommentBundle\Entity\Comment $commentaires
     * @return Agenda
     */
    public function addCommentaire(\TBN\CommentBundle\Entity\Comment $commentaires)
    {
        $this->commentaires[] = $commentaires;

        return $this;
    }

    /**
     * Remove commentaires
     *
     * @param \TBN\CommentBundle\Entity\Comment $commentaires
     */
    public function removeCommentaire(\TBN\CommentBundle\Entity\Comment $commentaires)
    {
        $this->commentaires->removeElement($commentaires);
    }

    /**
     * Get commentaires
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCommentaires()
    {
        return $this->commentaires;
    }

    /**
     * Set fbParticipations
     *
     * @param string $fbParticipations
     * @return Agenda
     */
    public function setFbParticipations($fbParticipations)
    {
        $this->fbParticipations = $fbParticipations;

        return $this;
    }

    /**
     * Get fbParticipations
     *
     * @return string
     */
    public function getFbParticipations()
    {
        return $this->fbParticipations;
    }

    /**
     * Set fbInterets
     *
     * @param string $fbInterets
     * @return Agenda
     */
    public function setFbInterets($fbInterets)
    {
        $this->fbInterets = $fbInterets;

        return $this;
    }

    /**
     * Get fbInterets
     *
     * @return string
     */
    public function getFbInterets()
    {
        return $this->fbInterets;
    }

    /**
     * Set participations
     *
     * @param string $participations
     * @return Agenda
     */
    public function setParticipations($participations)
    {
        $this->participations = $participations;

        return $this;
    }

    /**
     * Get participations
     *
     * @return string
     */
    public function getParticipations()
    {
        return $this->participations;
    }

    /**
     * Set interets
     *
     * @param string $interets
     * @return Agenda
     */
    public function setInterets($interets)
    {
        $this->interets = $interets;

        return $this;
    }

    /**
     * Get interets
     *
     * @return string
     */
    public function getInterets()
    {
        return $this->interets;
    }

    /**
     * Set facebookOwnerId
     *
     * @param string $facebookOwnerId
     * @return Agenda
     */
    public function setFacebookOwnerId($facebookOwnerId)
    {
        $this->facebookOwnerId = $facebookOwnerId;
    
        return $this;
    }

    /**
     * Get facebookOwnerId
     *
     * @return string 
     */
    public function getFacebookOwnerId()
    {
        return $this->facebookOwnerId;
    }

    /**
     * Set adresse
     *
     * @param string $adresse
     * @return Agenda
     */
    public function setAdresse($adresse)
    {
        $this->adresse = $adresse;
    
        return $this;
    }

    /**
     * Get adresse
     *
     * @return string 
     */
    public function getAdresse()
    {
        return $this->adresse;
    }

    /**
     * Set source
     *
     * @param string $source
     * @return Agenda
     */
    public function setSource($source)
    {
        $this->source = $source;
    
        return $this;
    }

    /**
     * Get source
     *
     * @return string 
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Set place
     *
     * @param \TBN\AgendaBundle\Entity\Place $place
     * @return Agenda
     */
    public function setPlace(\TBN\AgendaBundle\Entity\Place $place = null)
    {        
        $this->place = $place;

        return $this;
    }

    /**
     * Get place
     *
     * @return \TBN\AgendaBundle\Entity\Place 
     */
    public function getPlace()
    {
        return $this->place;
    }

    /**
     * Set isMigrated
     *
     * @param boolean $isMigrated
     * @return Agenda
     */
    public function setMigrated($isMigrated)
    {
        $this->isMigrated = $isMigrated;
    
        return $this;
    }

    /**
     * Get isMigrated
     *
     * @return boolean 
     */
    public function isMigrated()
    {
        return $this->isMigrated;
    }

    public function isTrustedLocation() {
	return $this->isTrustedLocation;
    }

    public function setTrustedLocation($isTrustedLocation) {
	$this->isTrustedLocation = $isTrustedLocation;
	return $this;
    }
    
    public function setId($id) {
        $this->id = $id;
        return $this;
    }

    public function toJSON()
    {
        return json_encode($this->toArray());
    }
    
    public function toArray() {
        return [
            'place' => $this->place ? $this->place->toArray() : null,
            'site' => $this->site ? $this->site->toArray() : null,
            'nom' => $this->nom,
            'descriptif' => $this->descriptif,
            'dateDebut' => $this->dateDebut,
            'dateFin' => $this->dateFin
        ];
    }

    /**
     * Set fbDateModification
     *
     * @param \DateTime $fbDateModification
     * @return Agenda
     */
    public function setFbDateModification($fbDateModification)
    {
        $this->fbDateModification = $fbDateModification;

        return $this;
    }

    /**
     * Get fbDateModification
     *
     * @return \DateTime 
     */
    public function getFbDateModification()
    {
        return $this->fbDateModification;
    }
}
