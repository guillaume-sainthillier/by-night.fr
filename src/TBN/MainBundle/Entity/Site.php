<?php

namespace TBN\MainBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;

/**
 * Site
 *
 * @ORM\Table(
 *             indexes={@ORM\Index(
 *                  name="recherche_site_idx",
 *                  columns={"subdomain"}
 * )})
 * @ORM\Entity(repositoryClass="TBN\MainBundle\Entity\SiteRepository")
 * @ExclusionPolicy("all")
 */
class Site
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Expose
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="subdomain", type="string", length=255)
     */
    protected $subdomain;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    /**
     * @var string
     *
     * @ORM\Column(name="adjectif_singulier", type="string", length=255)
     */
    protected $adjectifSingulier;

    /**
     * @var string
     *
     * @ORM\Column(name="adjectif_pluriel", type="string", length=255)
     */
    protected $adjectifPluriel;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="string", length=255)
     */
    protected $description;

    /**
     * @var string
     *
     * @ORM\Column(name="facebook_id_page", type="string", length=127, nullable=true)
     */
    protected $facebookIdPage;

    /**
     * @var string
     *
     * @ORM\Column(name="google_id_page", type="string", length=127, nullable=true)
     */
    protected $googleIdPage;

    /**
     * @var string
     *
     * @ORM\Column(name="twitter_id_page", type="string", length=127, nullable=true)
     */
    protected $twitterIdPage;

    /**
     * @var string
     *
     * @ORM\Column(name="twitter_id_widget", type="string", length=127, nullable=true)
     */
    protected $twitterIdWidget;

    /**
     * @var string
     *
     * @ORM\Column(name="twitter_url_widget", type="string", length=255, nullable=true)
     */
    protected $twitterURLWidget;

    /**
     * @var boolean
     *
     * @ORM\Column(name="is_actif", type="boolean")
     */
    protected $isActif;

    /**
     * @var boolean
     *
     * @ORM\Column(name="distance_max", type="float")
     */
    protected $distanceMax;

    /**
     * @var boolean
     *
     * @ORM\Column(name="latitude", type="float")
     */
    protected $latitude;

    /**
     * @var boolean
     *
     * @ORM\Column(name="longitude", type="float")
     */
    protected $longitude;

    public function __construct()
    {
        $this->isActif = false;
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
     * Set subdomain
     *
     * @param string $subdomain
     * @return Site
     */
    public function setSubdomain($subdomain)
    {
        $this->subdomain = $subdomain;

        return $this;
    }

    /**
     * Get subdomain
     *
     * @return string 
     */
    public function getSubdomain()
    {
        return $this->subdomain;
    }

    /**
     * Set nom
     *
     * @param string $nom
     * @return Site
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
     * Set adjectifSingulier
     *
     * @param string $adjectifSingulier
     * @return Site
     */
    public function setAdjectifSingulier($adjectifSingulier)
    {
        $this->adjectifSingulier = $adjectifSingulier;

        return $this;
    }

    /**
     * Get adjectifSingulier
     *
     * @return string 
     */
    public function getAdjectifSingulier()
    {
        return $this->adjectifSingulier;
    }

    /**
     * Set adjectifPluriel
     *
     * @param string $adjectifPluriel
     * @return Site
     */
    public function setAdjectifPluriel($adjectifPluriel)
    {
        $this->adjectifPluriel = $adjectifPluriel;

        return $this;
    }

    /**
     * Get adjectifPluriel
     *
     * @return string 
     */
    public function getAdjectifPluriel()
    {
        return $this->adjectifPluriel;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return Site
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string 
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set facebookIdPage
     *
     * @param string $facebookIdPage
     * @return Site
     */
    public function setFacebookIdPage($facebookIdPage)
    {
        $this->facebookIdPage = $facebookIdPage;

        return $this;
    }

    /**
     * Get facebookIdPage
     *
     * @return string 
     */
    public function getFacebookIdPage()
    {
        return $this->facebookIdPage;
    }

    /**
     * Set googleIdPage
     *
     * @param string $googleIdPage
     * @return Site
     */
    public function setGoogleIdPage($googleIdPage)
    {
        $this->googleIdPage = $googleIdPage;

        return $this;
    }

    /**
     * Get googleIdPage
     *
     * @return string 
     */
    public function getGoogleIdPage()
    {
        return $this->googleIdPage;
    }

    /**
     * Set twitterIdPage
     *
     * @param string $twitterIdPage
     * @return Site
     */
    public function setTwitterIdPage($twitterIdPage)
    {
        $this->twitterIdPage = $twitterIdPage;

        return $this;
    }

    /**
     * Get twitterIdPage
     *
     * @return string 
     */
    public function getTwitterIdPage()
    {
        return $this->twitterIdPage;
    }

    /**
     * Set twitterIdWidget
     *
     * @param string $twitterIdWidget
     * @return Site
     */
    public function setTwitterIdWidget($twitterIdWidget)
    {
        $this->twitterIdWidget = $twitterIdWidget;

        return $this;
    }

    /**
     * Get twitterIdWidget
     *
     * @return string 
     */
    public function getTwitterIdWidget()
    {
        return $this->twitterIdWidget;
    }

    /**
     * Set twitterURLWidget
     *
     * @param string $twitterURLWidget
     * @return Site
     */
    public function setTwitterURLWidget($twitterURLWidget)
    {
        $this->twitterURLWidget = $twitterURLWidget;

        return $this;
    }

    /**
     * Get twitterURLWidget
     *
     * @return string 
     */
    public function getTwitterURLWidget()
    {
        return $this->twitterURLWidget;
    }

    /**
     * Set isActif
     *
     * @param boolean $isActif
     * @return Site
     */
    public function setIsActif($isActif)
    {
        $this->isActif = $isActif;

        return $this;
    }

    /**
     * Get isActif
     *
     * @return boolean 
     */
    public function getIsActif()
    {
        return $this->isActif;
    }

    /**
     * Set distanceMax
     *
     * @param float $distanceMax
     * @return Site
     */
    public function setDistanceMax($distanceMax)
    {
        $this->distanceMax = $distanceMax;

        return $this;
    }

    /**
     * Get distanceMax
     *
     * @return float 
     */
    public function getDistanceMax()
    {
        return $this->distanceMax;
    }

    /**
     * Set latitude
     *
     * @param float $latitude
     * @return Site
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
     * @return Site
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
}
