<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\App;

use App\Entity\City;
use App\Entity\Country;

class Location
{
    private ?City $city = null;

    private ?Country $country = null;

    public function getId()
    {
        if (null !== $this->city) {
            return $this->city->getId();
        }

        if (null !== $this->country) {
            return $this->country->getId();
        }

        return 'unknown';
    }

    public function getAppName()
    {
        if (null !== $this->city) {
            return sprintf('%s By Night', $this->city->getName());
        }

        return sprintf('By Night %s', $this->country->getDisplayName());
    }

    public function getAtName()
    {
        if (null !== $this->city) {
            return sprintf('à %s', $this->city->getName());
        }

        if (null !== $this->country) {
            return $this->country->getAtDisplayName();
        }

        return '';
    }

    public function getName()
    {
        if (null !== $this->city) {
            return $this->city->getName();
        }

        if (null !== $this->country) {
            return $this->country->getDisplayName();
        }

        return '';
    }

    public function getSlug()
    {
        if (null !== $this->city) {
            return $this->city->getSlug();
        }

        if (null !== $this->country) {
            return $this->country->getSlug();
        }

        return 'unknown';
    }

    public function isCity()
    {
        return null !== $this->city;
    }

    public function isCountry()
    {
        return null !== $this->country;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }
}
