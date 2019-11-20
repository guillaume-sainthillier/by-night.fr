<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;

/**
 * ZipCity.
 *
 * @ORM\Table(name="zip_city", indexes={
 *     @ORM\Index(name="zip_city_postal_code_name_idx", columns={"country_id", "postal_code", "name"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\ZipCityRepository", readOnly=true)
 * @Serializer\ExclusionPolicy("ALL")
 */
class ZipCity
{
    /**
     * @var int
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue("AUTO")
     * @Serializer\Groups({"list_event", "list_city", "list_user"})
     * @Serializer\Expose
     */
    protected $id;

    /**
     * @Gedmo\Slug(fields={"postalCode", "name"}, updatable=false)
     * @ORM\Column(length=201, unique=true)
     */
    protected $slug;

    /**
     * @var Country
     * @ORM\ManyToOne(targetEntity="App\Entity\Country", fetch="EXTRA_LAZY")
     */
    protected $country;

    /**
     * @var string
     * @ORM\Column(type="string", length=20)
     */
    protected $postalCode;

    /**
     * @var string
     * @ORM\Column(type="string", length=180)
     */
    protected $name;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    protected $latitude;

    /**
     * @var float
     * @ORM\Column(type="float")
     */
    protected $longitude;

    /**
     * @var string
     * @ORM\Column(name="admin1_code", type="string", length=20)
     */
    protected $admin1Code;

    /**
     * @var string|null
     * @ORM\Column(name="admin1_name", type="string", length=100, nullable=true)
     */
    protected $admin1Name;

    /**
     * @var string
     * @ORM\Column(name="admin2_code", type="string", length=80)
     */
    protected $admin2Code;

    /**
     * @var string|null
     * @ORM\Column(name="admin2_name", type="string", length=100, nullable=true)
     */
    protected $admin2Name;

    /**
     * @var City
     * @ORM\ManyToOne(targetEntity="App\Entity\City", fetch="EXTRA_LAZY")
     */
    protected $parent;

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
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Set postalCode.
     *
     * @param string $postalCode
     *
     * @return ZipCity
     */
    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    /**
     * Get postalCode.
     *
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * Set name.
     *
     * @param string $name
     *
     * @return ZipCity
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
     * Set latitude.
     *
     * @param float $latitude
     *
     * @return ZipCity
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
     * @return ZipCity
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
     * Set admin1Code.
     *
     * @param string $admin1Code
     *
     * @return ZipCity
     */
    public function setAdmin1Code($admin1Code)
    {
        $this->admin1Code = $admin1Code;

        return $this;
    }

    /**
     * Get admin1Code.
     *
     * @return string
     */
    public function getAdmin1Code()
    {
        return $this->admin1Code;
    }

    /**
     * Set admin2Code.
     *
     * @param string $admin2Code
     *
     * @return ZipCity
     */
    public function setAdmin2Code($admin2Code)
    {
        $this->admin2Code = $admin2Code;

        return $this;
    }

    /**
     * Get admin2Code.
     *
     * @return string
     */
    public function getAdmin2Code()
    {
        return $this->admin2Code;
    }

    /**
     * Set country.
     *
     * @param Country $country
     *
     * @return ZipCity
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
     * Set slug.
     *
     * @param string $slug
     *
     * @return ZipCity
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
     * Set parent.
     *
     * @param City $parent
     *
     * @return ZipCity
     */
    public function setParent(City $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return City
     */
    public function getParent()
    {
        return $this->parent;
    }

    public function getAdmin1Name(): ?string
    {
        return $this->admin1Name;
    }

    public function setAdmin1Name(?string $admin1Name): ZipCity
    {
        $this->admin1Name = $admin1Name;

        return $this;
    }

    public function getAdmin2Name(): ?string
    {
        return $this->admin2Name;
    }

    public function setAdmin2Name(?string $admin2Name): ZipCity
    {
        $this->admin2Name = $admin2Name;

        return $this;
    }
}
