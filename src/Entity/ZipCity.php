<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as Serializer;

/**
 * ZipCity.
 *
 * @ORM\Table(name="zip_city", indexes={
 @ORM\Index(name="zip_city_postal_code_name_idx", columns={"name", "postal_code"})
 * })
 * @ORM\Entity(repositoryClass="App\Repository\ZipCityRepository", readOnly=true)
 * @Serializer\ExclusionPolicy("ALL")
 */
class ZipCity
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue("AUTO")
     * @Serializer\Groups({"list_event", "list_city", "list_user"})
     * @Serializer\Expose()
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
     * @ORM\Column(name="postal_code", type="string", length=20)
     */
    protected $postalCode;

    /**
     * @var string
     * @ORM\Column(name="name", type="string", length=180)
     */
    protected $name;

    /**
     * @var float
     * @ORM\Column(name="latitude", type="float")
     */
    protected $latitude;

    /**
     * @var float
     * @ORM\Column(name="longitude", type="float")
     */
    protected $longitude;

    /**
     * @var string
     * @ORM\Column(name="admin1_code", type="string", length=20)
     */
    protected $admin1Code;

    /**
     * @var string
     * @ORM\Column(name="admin2_code", type="string", length=80)
     */
    protected $admin2Code;

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
     * @param \App\Entity\Country $country
     *
     * @return ZipCity
     */
    public function setCountry(\App\Entity\Country $country = null)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country.
     *
     * @return \App\Entity\Country
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
     * @param \App\Entity\City $parent
     *
     * @return ZipCity
     */
    public function setParent(\App\Entity\City $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return \App\Entity\City
     */
    public function getParent()
    {
        return $this->parent;
    }
}
