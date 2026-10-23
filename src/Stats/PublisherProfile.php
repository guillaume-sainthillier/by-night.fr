<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Enum\PublisherDistinction;

/**
 * What a publisher's page tells of it (PublisherProfileProvider): its events, where and what they are, the audience
 * they reach, the distinctions these earn it, and how complete its page is.
 */
final readonly class PublisherProfile
{
    /**
     * @param list<PublisherDistinction>                                                                        $distinctions
     * @param list<array{name: string, slug: string, events: int, firstYear: int, lastYear: int}>               $cities       the busiest cities
     * @param list<array{name: string, slug: string, locationSlug: string, cityName: string|null, events: int}> $places       the busiest venues
     * @param list<array{id: int, name: string, events: int, firstYear: int, lastYear: int}>                    $categories   the busiest categories
     */
    public function __construct(
        public PublisherStats $stats,
        public PublisherCompletion $completion,
        public array $distinctions,
        public array $cities,
        public array $places,
        public array $categories,
    ) {
    }
}
