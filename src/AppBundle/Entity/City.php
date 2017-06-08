<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Exclude;

/**
 * City.
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\CityRepository", readOnly=true)
 * @ExclusionPolicy("NONE")
 */
class City extends AdminZone
{
    /**
     * @var AdminZone2
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\AdminZone2", fetch="EAGER")
     * @Exclude
     */
    protected $parent;

    /**
     * Set id.
     *
     * @param int $id
     *
     * @return City
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
     * @return City
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
     * @return City
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
     * @return City
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
     * @return City
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
     * @return City
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
     * Set parent.
     *
     * @param \AppBundle\Entity\AdminZone2 $parent
     *
     * @return City
     */
    public function setParent(\AppBundle\Entity\AdminZone2 $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return \AppBundle\Entity\AdminZone2
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set country.
     *
     * @param \AppBundle\Entity\Country $country
     *
     * @return City
     */
    public function setCountry(\AppBundle\Entity\Country $country = null)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country.
     *
     * @return \AppBundle\Entity\Country
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
     * @return City
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
     * @return City
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
