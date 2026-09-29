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
 * How many published events to come of a city or a country have a category, as last counted by
 * app:events:count-upcoming: the category shortcuts of the portals and of the search suggestions, read from a few
 * rows instead of grouping every event to come of the zone on each page (~0.3 s for France).
 *
 * A row counts either a city or a country, never both.
 */
#[ORM\Entity]
class UpcomingCategory
{
    use EntityIdentityTrait;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Tag::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Tag $tag,

        #[ORM\Column(type: Types::INTEGER)]
        private int $events,

        #[ORM\ManyToOne(targetEntity: City::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?City $city = null,

        #[ORM\ManyToOne(targetEntity: Country::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Country $country = null,
    ) {
    }

    public function getTag(): Tag
    {
        return $this->tag;
    }

    public function getEvents(): int
    {
        return $this->events;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }
}
