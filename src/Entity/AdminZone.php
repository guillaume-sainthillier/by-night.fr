<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation\Exclude;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;

/**
 * Info.
 *
 * @ORM\Table(name="admin_zone", indexes={
 @ORM\Index(name="admin_zone_type_name_idx", columns={"type", "name"}),
 @ORM\Index(name="admin_zone_type_population_idx", columns={"type", "population"})
 * })
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string", length=10)
 * @ORM\DiscriminatorMap({"PPL" = "City","ADM1" = "AdminZone1", "ADM2" = "AdminZone2"})
 * @ORM\Entity(repositoryClass="App\Repository\AdminZoneRepository", readOnly=true)
 * @ORM\HasLifecycleCallbacks()
 * @ExclusionPolicy("NONE")
 */
abstract class AdminZone
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @Groups({"list_event", "list_city", "list_user"})
     */
    protected $id;

    /**
     * @Gedmo\Slug(fields={"name"}, updatable=false)
     * @ORM\Column(length=200, unique=true)
     * @Exclude
     */
    protected $slug;

    /**
     * @var string
     * @ORM\Column(name="name", type="string", length=200)
     * @Groups({"list_city"})
     */
    protected $name;

    /**
     * @var float
     * @ORM\Column(name="latitude", type="float")
     * @Groups({"list_city"})
     */
    protected $latitude;

    /**
     * @var float
     * @ORM\Column(name="longitude", type="float")
     * @Groups({"list_city"})
     */
    protected $longitude;

    /**
     * @var int
     * @ORM\Column(name="population", type="integer")
     * @Groups({"list_city"})
     */
    protected $population;

    /**
     * @var Country
     * @ORM\ManyToOne(targetEntity="App\Entity\Country", fetch="EXTRA_LAZY")
     * @Groups({"list_city"})
     */
    protected $country;

    /**
     * @var string
     * @ORM\Column(name="admin1_code", type="string", length=20, nullable=true)
     * @Exclude
     */
    protected $admin1Code;

    /**
     * @var string
     * @ORM\Column(name="admin2_code", type="string", length=80, nullable=true)
     * @Exclude
     */
    protected $admin2Code;

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return AdminZone
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
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
     * Set name.
     *
     * @param string $name
     *
     * @return AdminZone
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
     * @return AdminZone
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
     * @return AdminZone
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
     * @return AdminZone
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
     * @return AdminZone
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
     * @return AdminZone
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
     * @return AdminZone
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
     * Set population.
     *
     * @param int $population
     *
     * @return AdminZone
     */
    public function setPopulation($population)
    {
        $this->population = $population;

        return $this;
    }

    /**
     * Get population.
     *
     * @return int
     */
    public function getPopulation()
    {
        return $this->population;
    }
}
