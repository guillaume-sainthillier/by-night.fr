<?php

namespace App\Entity;

use App\Geolocalize\GeolocalizeInterface;
use App\Reject\Reject;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Expose;
use JMS\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Place.
 *
 * @ORM\Table(name="Place", indexes={
 *   @ORM\Index(name="place_nom_idx", columns={"nom"}),
 *   @ORM\Index(name="place_slug_idx", columns={"slug"}),
 * })
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks
 * @ExclusionPolicy("all")
 * @ORM\Entity(repositoryClass="App\Repository\PlaceRepository")
 */
class Place implements GeolocalizeInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"list_event"})
     * @Expose
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="rue", type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private $rue;

    /**
     * @var float
     *
     * @ORM\Column(name="latitude", type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private $latitude;

    /**
     * @var float
     *
     * @ORM\Column(name="longitude", type="float", nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    private $longitude;

    /**
     * @var string
     *
     * @ORM\Column(name="nom", type="string", length=255)
     * @Assert\NotBlank(message="Vous devez indiquer le lieu de votre événement")
     * @Groups({"list_event"})
     * @Expose
     */
    private $nom;

    /**
     * @var string
     * @Gedmo\Slug(fields={"nom"})
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
     */
    private $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="path", type="string", length=255, nullable=true)
     */
    private $path;

    /**
     * @var string
     *
     * @ORM\Column(name="url", type="string", length=255, nullable=true)
     */
    private $url;

    /**
     * @ORM\Column(name="ville", type="string", length=127, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $ville;

    /**
     * @ORM\Column(name="code_postal", type="string", length=7, nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $codePostal;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Site", cascade={"persist", "merge"})
     * @ORM\JoinColumn(nullable=true)
     */
    protected $site;

    /**
     * @var string
     *
     * @ORM\Column(name="facebook_id", type="string", length=256, nullable=true)
     */
    protected $facebookId;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\City")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $city;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ZipCity")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $zipCity;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Country")
     * @ORM\JoinColumn(nullable=true)
     * @Groups({"list_event"})
     * @Expose
     */
    protected $country;

    /**
     * @ORM\Column(name="is_junk", type="boolean", nullable=true)
     */
    protected $isJunk;

    /**
     * @var string
     */
    protected $countryName;

    /**
     * @var Reject
     */
    protected $reject;

    public function setReject(Reject $reject = null)
    {
        $this->reject = $reject;

        return $this;
    }

    public function getReject()
    {
        return $this->reject;
    }

    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param $country
     *
     * @return Place
     */
    public function setCountryName($country)
    {
        $this->countryName = $country;

        return $this;
    }

    /**
     * @return string
     */
    public function getCountryName()
    {
        return $this->countryName;
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
     * Set rue.
     *
     * @param string $rue
     *
     * @return Place
     */
    public function setRue($rue)
    {
        $this->rue = $rue;

        return $this;
    }

    /**
     * Get rue.
     *
     * @return string
     */
    public function getRue()
    {
        return $this->rue;
    }

    /**
     * Set latitude.
     *
     * @param float $latitude
     *
     * @return Place
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
     * @return Place
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
     * Set nom.
     *
     * @param string $nom
     *
     * @return Place
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
     * Set slug.
     *
     * @param string $slug
     *
     * @return Place
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
     * Set path.
     *
     * @param string $path
     *
     * @return Place
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
     * Set url.
     *
     * @param string $url
     *
     * @return Place
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
     * Set ville.
     *
     * @param string $ville
     *
     * @return Place
     */
    public function setVille($ville)
    {
        $this->ville = $ville;

        return $this;
    }

    /**
     * Get ville.
     *
     * @return string
     */
    public function getVille()
    {
        return $this->ville;
    }

    /**
     * Set codePostal.
     *
     * @param string $codePostal
     *
     * @return Place
     */
    public function setCodePostal($codePostal)
    {
        $this->codePostal = $codePostal;

        return $this;
    }

    /**
     * Get codePostal.
     *
     * @return string
     */
    public function getCodePostal()
    {
        return $this->codePostal;
    }

    /**
     * Set facebookId.
     *
     * @param string $facebookId
     *
     * @return Place
     */
    public function setFacebookId($facebookId)
    {
        $this->facebookId = $facebookId;

        return $this;
    }

    /**
     * Get facebookId.
     *
     * @return string
     */
    public function getFacebookId()
    {
        return $this->facebookId;
    }

    /**
     * Set site.
     *
     * @param Site $site
     *
     * @return Place
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

    public function __toString()
    {
        return \sprintf('#%s (%s)', $this->id ?: '?', $this->getNom());
    }

    /**
     * Set city.
     *
     * @param City $city
     *
     * @return Place
     */
    public function setCity(City $city = null)
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Get city.
     *
     * @return City
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * Set zipCity.
     *
     * @param ZipCity $zipCity
     *
     * @return Place
     */
    public function setZipCity(ZipCity $zipCity = null)
    {
        $this->zipCity = $zipCity;

        return $this;
    }

    /**
     * Get zipCity.
     *
     * @return ZipCity
     */
    public function getZipCity()
    {
        return $this->zipCity;
    }

    /**
     * Set country.
     *
     * @param Country $country
     *
     * @return Place
     */
    public function setCountry(Country $country = null)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country.
     *
     * @return Country
     */
    public function getCountry()
    {
        return $this->country;
    }

    /**
     * Set isJunk.
     *
     * @param bool $isJunk
     *
     * @return Place
     */
    public function setJunk($isJunk)
    {
        $this->isJunk = $isJunk;

        return $this;
    }

    /**
     * Get isJunk.
     *
     * @return bool
     */
    public function isJunk()
    {
        return $this->isJunk;
    }

    /**
     * Set isJunk.
     *
     * @param bool $isJunk
     *
     * @return Place
     */
    public function setIsJunk($isJunk)
    {
        $this->isJunk = $isJunk;

        return $this;
    }

    /**
     * Get isJunk.
     *
     * @return bool
     */
    public function getIsJunk()
    {
        return $this->isJunk;
    }
}
