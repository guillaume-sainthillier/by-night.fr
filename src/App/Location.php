<?php

namespace App\App;

use App\Entity\City;
use App\Entity\Country;

class Location
{
    /** @var City|null */
    private $city;

    /** @var Country|null */
    private $country;

    public function getAppName()
    {
        if ($this->city) {
            return sprintf('%s By Night', $this->city->getName());
        } else {
            return sprintf('By Night %s', $this->country->getName());
        }
    }

    public function getAtValue()
    {
        if ($this->city) {
            return 'à';
        } else {
            return 'en';
        }
    }

    public function getAtName()
    {
        if ($this->city) {
            return sprintf('à %s', $this->city->getName());
        } else {
            return sprintf('en %s', $this->country->getName());
        }
    }

    public function getName()
    {
        return $this->city ? $this->city->getName() : $this->country->getName();
    }

    public function getSlug()
    {
        return $this->city ? $this->city->getSlug() : $this->country->getSlug();
    }

    public function isCity()
    {
        return null !== $this->city;
    }

    public function isCountry()
    {
        return null !== $this->country;
    }

    /**
     * @return City|null
     */
    public function getCity(): ?City
    {
        return $this->city;
    }

    /**
     * @param City|null $city
     * @return self
     */
    public function setCity(?City $city): self
    {
        $this->city = $city;
        return $this;
    }

    /**
     * @return Country|null
     */
    public function getCountry(): ?Country
    {
        return $this->country;
    }

    /**
     * @param Country|null $country
     * @return self
     */
    public function setCountry(?Country $country): self
    {
        $this->country = $country;
        return $this;
    }
}