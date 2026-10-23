<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Entity\Publisher;
use App\Enum\PublisherDistinction;
use App\Repository\EventRepository;

/**
 * Gathers what a publisher's page shows and its distinctions are earned from.
 */
final readonly class PublisherProfileProvider
{
    /** The busiest cities, venues and categories listed */
    public const int TOP = 5;

    public function __construct(
        private EventRepository $eventRepository,
    ) {
    }

    public function get(Publisher $publisher): PublisherProfile
    {
        $totals = $this->eventRepository->getPublisherTotals($publisher);
        $cities = $this->eventRepository->findPublisherCities($publisher);
        $places = $this->eventRepository->findPublisherPlaces($publisher, self::TOP);

        $stats = new PublisherStats(
            events: $totals['events'],
            upcomingEvents: $totals['upcoming'],
            freeEvents: $totals['free'],
            participations: $totals['participations'],
            interests: $totals['interests'],
            cities: \count($cities),
            busiestPlaceEvents: $places[0]['events'] ?? 0,
            firstYear: $totals['firstYear'],
            activeMonths: $totals['activeMonths'],
        );
        $completion = PublisherCompletion::of($publisher, $stats);

        return new PublisherProfile(
            stats: $stats,
            completion: $completion,
            distinctions: PublisherDistinction::earnedBy($stats, $completion),
            cities: \array_slice($cities, 0, self::TOP),
            places: $places,
            categories: \array_slice($this->eventRepository->findPublisherCategories($publisher), 0, self::TOP),
        );
    }
}
