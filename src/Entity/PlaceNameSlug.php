<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Entity;

use App\Repository\PlaceNameSlugRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Stringable;

/**
 * A normalized name a place has been seen under, used as an indexed fast path
 * for place de-duplication (the twin of PlaceMetadata, which indexes external ids).
 *
 * A place accumulates one slug per distinct source name variant, so repeated
 * imports resolve via an index seek instead of a city-wide fuzzy scan.
 */
#[ORM\Entity(repositoryClass: PlaceNameSlugRepository::class)]
#[ORM\Index(name: 'place_name_slug_city_idx', columns: ['city_id', 'slug'])]
#[ORM\Index(name: 'place_name_slug_country_idx', columns: ['country_id', 'slug'])]
class PlaceNameSlug implements Stringable
{
    use EntityIdentityTrait;

    #[ORM\ManyToOne(targetEntity: Place::class, inversedBy: 'nameSlugs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Place $place = null;

    #[ORM\ManyToOne(targetEntity: City::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?City $city = null;

    #[ORM\ManyToOne(targetEntity: Country::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Country $country = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $slug = null;

    public function __toString(): string
    {
        return (string) $this->slug;
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): self
    {
        $this->place = $place;

        return $this;
    }

    public function getCity(): ?City
    {
        return $this->city;
    }

    public function setCity(?City $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }
}
