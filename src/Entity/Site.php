<?php

namespace App\Entity;

use App\Geolocalize\BoundaryInterface;
use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use function sprintf;

/**
 * Site.
 *
 * @ORM\Table(name="Site",
 *      indexes={@ORM\Index(name="recherche_site_idx", columns={"subdomain"})})
 * @ORM\Entity(repositoryClass="App\Repository\SiteRepository")
 * @ExclusionPolicy("all")
 */
class Site implements BoundaryInterface
{
    /**
     * @var int
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
     * @var bool
     *
     * @ORM\Column(name="is_actif", type="boolean")
     */
    protected $isActif;

    /**
     * @var float
     *
     * @ORM\Column(name="distance_max", type="float")
     */
    protected $distanceMax;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="float")
     */
    protected $latitude;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="float")
     */
    protected $longitude;

    public function __construct()
    {
        $this->isActif = true;
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
     * Set subdomain.
     *
     * @param string $subdomain
     *
     * @return Site
     */
    public function setSubdomain($subdomain)
    {
        $this->subdomain = $subdomain;

        return $this;
    }

    /**
     * Get subdomain.
     *
     * @return string
     */
    public function getSubdomain()
    {
        return $this->subdomain;
    }

    /**
     * Set nom.
     *
     * @param string $nom
     *
     * @return Site
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
     * Get twitterIdWidget.
     *
     * @return string
     */
    public function getTwitterIdWidget()
    {
        return $this->twitterIdWidget;
    }

    /**
     * Set twitterURLWidget.
     *
     * @param string $twitterURLWidget
     *
     * @return Site
     */
    public function setTwitterURLWidget($twitterURLWidget)
    {
        $this->twitterURLWidget = $twitterURLWidget;

        return $this;
    }

    /**
     * Get twitterURLWidget.
     *
     * @return string
     */
    public function getTwitterURLWidget()
    {
        return $this->twitterURLWidget;
    }

    /**
     * Set isActif.
     *
     * @param bool $isActif
     *
     * @return Site
     */
    public function setActif($isActif)
    {
        $this->isActif = $isActif;

        return $this;
    }

    public function setIsActif($isActif)
    {
        return $this->setActif($isActif);
    }

    /**
     * Get isActif.
     *
     * @return bool
     */
    public function isActif()
    {
        return $this->isActif;
    }

    /**
     * Set distanceMax.
     *
     * @param float $distanceMax
     *
     * @return Site
     */
    public function setDistanceMax($distanceMax)
    {
        $this->distanceMax = $distanceMax;

        return $this;
    }

    /**
     * Get distanceMax.
     *
     * @return float
     */
    public function getDistanceMax()
    {
        return $this->distanceMax;
    }

    /**
     * Set latitude.
     *
     * @param float $latitude
     *
     * @return Site
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
     * @return Site
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

    public function __toString()
    {
        return sprintf('#%s (%s)', $this->id ?: '?', $this->getNom());
    }

    /**
     * Set twitterIdWidget.
     *
     * @param string $twitterIdWidget
     *
     * @return Site
     */
    public function setTwitterIdWidget($twitterIdWidget)
    {
        $this->twitterIdWidget = $twitterIdWidget;

        return $this;
    }

    /**
     * Get isActif.
     *
     * @return bool
     */
    public function getIsActif()
    {
        return $this->isActif;
    }
}
