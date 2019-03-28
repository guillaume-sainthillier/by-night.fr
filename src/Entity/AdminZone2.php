<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * AdminZone2.
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="App\Repository\AdminZone2Repository", readOnly=true)
 */
class AdminZone2 extends AdminZone
{
    /**
     * @var AdminZone1
     * @ORM\ManyToOne(targetEntity="App\Entity\AdminZone1", fetch="EXTRA_LAZY")
     */
    protected $parent;

    /**
     * Set parent.
     *
     * @param AdminZone1 $parent
     *
     * @return AdminZone2
     */
    public function setParent(AdminZone1 $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Get parent.
     *
     * @return AdminZone1
     */
    public function getParent()
    {
        return $this->parent;
    }
}
