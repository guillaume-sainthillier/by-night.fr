<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A former slug of a city: its pages (/{slug}, its agenda, its events) redirect to the city's current ones. The cities
 * of a country that prefixes its cities' URLs had a slug of their own ("geneve-1" is "suisse/geneve").
 */
#[ORM\Entity]
#[ORM\Index(name: 'city_legacy_slug_slug_idx', columns: ['slug'])]
class CityLegacySlug
{
    use EntityIdentityTrait;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: City::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private City $city,

        #[ORM\Column(type: Types::STRING, length: 200)]
        private string $slug,
    ) {
    }

    public function getCity(): City
    {
        return $this->city;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
