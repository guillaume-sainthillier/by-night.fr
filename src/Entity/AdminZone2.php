<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AdminZone2Repository", readOnly=true)
 */
class AdminZone2 extends AdminZone
{
    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AdminZone1", fetch="EXTRA_LAZY")
     */
    protected ?AdminZone1 $parent = null;

    /**
     * Get parent.
     */
    public function getParent(): ?AdminZone1
    {
        return $this->parent;
    }

    /**
     * Set parent.
     *
     * @return AdminZone2
     */
    public function setParent(AdminZone1 $parent = null)
    {
        $this->parent = $parent;

        return $this;
    }
}
