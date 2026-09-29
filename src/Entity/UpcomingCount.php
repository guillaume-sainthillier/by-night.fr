<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Enum\AgendaType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * How many published events to come of a city or a country have a category, or are listed on an agenda type page,
 * as last counted by UpcomingEventCounter: the category shortcuts of the portals and of the search suggestions and
 * the counts of the portals' type cards, read from a few rows instead of grouping every event to come of the zone
 * on each page (~0.3 s for France).
 *
 * A row counts one zone, either a city or a country, and one value of one dimension: a category ($tag) or an agenda
 * type ($agendaType), the column of the other dimension left null.
 */
#[ORM\Entity]
class UpcomingCount
{
    use EntityIdentityTrait;

    public function __construct(
        #[ORM\Column(type: Types::INTEGER)]
        private int $events,

        #[ORM\ManyToOne(targetEntity: City::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?City $city = null,

        #[ORM\ManyToOne(targetEntity: Country::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Country $country = null,

        #[ORM\ManyToOne(targetEntity: Tag::class)]
        #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
        private ?Tag $tag = null,

        #[ORM\Column(type: Types::STRING, length: 15, nullable: true, enumType: AgendaType::class)]
        private ?AgendaType $agendaType = null,
    ) {
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

    public function getTag(): ?Tag
    {
        return $this->tag;
    }

    public function getAgendaType(): ?AgendaType
    {
        return $this->agendaType;
    }
}
