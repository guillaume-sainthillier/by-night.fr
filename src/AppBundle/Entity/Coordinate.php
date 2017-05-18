<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * City
 *
 * @ORM\Table()
 * @ORM\Entity()
 */
class Coordinate
{
    /**
     * @var float
     * @ORM\Column(name="latitude", type="float")
     * @ORM\Id
     */
    protected $latitude;

    /**
     * @var float
     * @ORM\Column(name="longitude", type="float")
     * @ORM\Id
     */
    protected $longitude;

    /**
     * @var Country
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\Country", fetch="EXTRA_LAZY")
     * @ORM\JoinColumn(nullable=true)
     */
    protected $country;

    /**
     * Set latitude
     *
     * @param float $latitude
     *
     * @return Coordinate
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
     *
     * @return Coordinate
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
     * Set country
     *
     * @param \AppBundle\Entity\Country $country
     *
     * @return Coordinate
     */
    public function setCountry(\AppBundle\Entity\Country $country = null)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Get country
     *
     * @return \AppBundle\Entity\Country
     */
    public function getCountry()
    {
        return $this->country;
    }
}
