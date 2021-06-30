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
use App\Contracts\ExternalIdentifiableInterface;
use App\Dependency\Dependency;
use App\Dependency\DependencyCatalogue;
use App\Reject\Reject;

class PlaceDto implements ExternalIdentifiableInterface, DependenciableInterface
{
    use DtoExternalIdentifiableTrait;

    /** @var int|null */
    public $id;

    /** @var string|null */
    public $name;

    /** @var string|null */
    public $postalCode;

    /** @var CityDto|null */
    public $city;

    /** @var string|null */
    public $street;

    /** @var float|null */
    public $latitude;

    /** @var float|null */
    public $longitude;

    /** @var CountryDto|null */
    public $country;

    /** @var Reject|null */
    public $reject;

    /**
     * {@inheritDoc}
     */
    public function getDependencyCatalogue(): DependencyCatalogueInterface
    {
        $catalogue = new DependencyCatalogue();
        if (null !== $this->city) {
            $catalogue->add(new Dependency($this->city));
        }

        if (null !== $this->country) {
            $catalogue->add(new Dependency($this->country));
        }

        return $catalogue;
    }
}
