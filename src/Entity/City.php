<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CityRepository", readOnly=true)
 * @ExclusionPolicy("NONE")
 */
class City extends AdminZone
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AdminZone", fetch="EAGER")
     * @Groups({"list_city"})
     */
    protected ?AdminZone $parent = null;

    /**
     * @Groups({"list_city"})
     */
    protected ?Country $country = null;

    public function __toString()
    {
        return $this->getFullName();
    }

    public function getFullName()
    {
        $parts = [];
        if ($this->getParent()) {
            $parts[] = $this->getParent()->getName();
        }
        $parts[] = $this->getCountry()->getName();

        return \sprintf('%s (%s)', $this->getName(), \implode(', ', $parts));
    }

    /**
     * Get parent.
     *
     * @return AdminZone
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Set parent.
     *
     * @param AdminZone $parent
     *
     * @return City
     */
    public function setParent(AdminZone $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): AdminZone
    {
        $this->country = $country;

        return $this;
    }
}
