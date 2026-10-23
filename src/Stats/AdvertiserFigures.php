<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

/**
 * The key figures of the advertisers' page (/annonceurs), all read from the database or the code, never typed in: see
 * AdvertiserFiguresProvider. No audience figure: the site keeps none it could publish.
 */
final readonly class AdvertiserFigures
{
    /**
     * @param list<string> $agendaSources    the open agendas and venues imported, by name
     * @param list<string> $ticketingSources the ticketing sites imported with an affiliate link, by name
     */
    public function __construct(
        /** The published events to come, as last counted by UpcomingEventCounter */
        public int $upcomingEvents,
        /** The cities with events to come */
        public int $cities,
        /** The countries and territories with events to come */
        public int $countries,
        /** The open member accounts */
        public int $members,
        /** The events the members published themselves, imports aside */
        public int $memberEvents,
        public array $agendaSources,
        public array $ticketingSources,
    ) {
    }

    public function sources(): int
    {
        return \count($this->agendaSources) + \count($this->ticketingSources);
    }

    /**
     * @return array<string, int|list<string>>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param array<string, mixed> $figures what toArray() returned
     */
    public static function fromArray(array $figures): self
    {
        return new self(
            (int) $figures['upcomingEvents'],
            (int) $figures['cities'],
            (int) $figures['countries'],
            (int) $figures['members'],
            (int) $figures['memberEvents'],
            array_values(array_map(strval(...), (array) $figures['agendaSources'])),
            array_values(array_map(strval(...), (array) $figures['ticketingSources'])),
        );
    }
}
