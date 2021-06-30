<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Dto;

use App\Contracts\DependenciableInterface;
use App\Contracts\DependencyCatalogueInterface;
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;

class CityDto implements DependenciableInterface
{
    /** @var int|null */
    public $id;

    /** @var string|null */
    public $name;

    /** @var CountryDto|null */
    public $country;

    /**
     * {@inheritDoc}
     */
    public function getDependencyCatalogue(): DependencyCatalogueInterface
    {
        $catalogue = new DependencyCatalogue();
        if (null !== $this->country) {
            $catalogue->add(new Dependency($this->country));
        }

        return $catalogue;
    }
}
