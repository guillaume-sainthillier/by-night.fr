<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\ExclusionPolicy;
use JMS\Serializer\Annotation\Groups;

/**
 * City.
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\CityRepository", readOnly=true)
 * @ExclusionPolicy("NONE")
 */
class City extends AdminZone
{
    /**
     * @var AdminZone
     * @ORM\ManyToOne(targetEntity="App\Entity\AdminZone", fetch="EAGER")
     * @Groups({"list_city"})
     */
    protected $parent;

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
     * Set parent.
     *
     * @param \App\Entity\AdminZone $parent
     *
     * @return City
     */
    public function setParent(\App\Entity\AdminZone $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return \App\Entity\AdminZone
     */
    public function getParent()
    {
        return $this->parent;
    }
}
