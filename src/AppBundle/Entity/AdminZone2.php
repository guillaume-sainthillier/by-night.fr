<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AdminZone2.
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="AppBundle\Repository\AdminZone2Repository", readOnly=true)
 */
class AdminZone2 extends AdminZone
{
    /**
     * @var AdminZone1
     * @ORM\ManyToOne(targetEntity="AppBundle\Entity\AdminZone1", fetch="EXTRA_LAZY")
     */
    protected $parent;

    /**
     * Set parent.
     *
     * @param \AppBundle\Entity\AdminZone1 $parent
     *
     * @return AdminZone2
     */
    public function setParent(\AppBundle\Entity\AdminZone1 $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return \AppBundle\Entity\AdminZone1
     */
    public function getParent()
    {
        return $this->parent;
    }
}
