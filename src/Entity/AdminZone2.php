<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\AdminZone2Repository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminZone2Repository::class, readOnly: true)]
class AdminZone2 extends AdminZone
{
    #[ORM\ManyToOne(targetEntity: AdminZone1::class, fetch: 'EXTRA_LAZY')]
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
     */
    public function setParent(AdminZone1 $parent = null): self
    {
        $this->parent = $parent;

        return $this;
    }
}
