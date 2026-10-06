<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Search;

/**
 * What a member's event says of itself before it is published, to look for the events already on the site that it
 * may repeat (EventElasticaRepository::createDuplicateCandidatesQuery()): its name, its dates, and where it takes
 * place, by its coordinates or else by its city.
 */
final readonly class DuplicateSearch
{
    /**
     * @param non-empty-string $name
     * @param list<DateRange>  $dates    the dates of the event, each a closed period
     * @param int|null         $excluded the event itself, when it is already saved (a draft about to be published)
     */
    public function __construct(
        public string $name,
        public array $dates,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $postalCode = null,
        public ?string $cityName = null,
        public ?int $excluded = null,
    ) {
    }

    public function hasCoordinates(): bool
    {
        return null !== $this->latitude && null !== $this->longitude;
    }
}
