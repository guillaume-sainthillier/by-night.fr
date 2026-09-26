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
 * The slug of a place merged into another one by app:places:merge-duplicates: the agenda page it named
 * (/{location}/agenda/sortir-a/{slug}) redirects to the page of the place that took its events.
 */
#[ORM\Entity]
#[ORM\Index(name: 'place_legacy_slug_slug_idx', columns: ['slug'])]
class PlaceLegacySlug
{
    use EntityIdentityTrait;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Place::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Place $place,

        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $slug,
    ) {
    }

    public function getPlace(): Place
    {
        return $this->place;
    }

    public function setPlace(Place $place): self
    {
        $this->place = $place;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
