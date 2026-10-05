<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Enum\MemberDistinction;

/**
 * What a member's profile tells of them (MemberProfileProvider): their outings, where and what they go to, and the
 * distinctions these earn them. The counts are about the published events of their calendar, unless said otherwise.
 */
final readonly class MemberProfile
{
    /**
     * @param list<MemberDistinction>                                                                           $distinctions
     * @param list<array{name: string, slug: string, events: int, firstYear: int, lastYear: int}>               $cities         every city, the busiest first
     * @param list<array{name: string, slug: string, locationSlug: string, cityName: string|null, events: int}> $places         the busiest venues
     * @param list<array{id: int, name: string, events: int, firstYear: int, lastYear: int}>                    $categories     every category, the busiest first
     * @param array<int, list<string>>                                                                          $categoryThemes the themes of the busiest categories, by category id
     */
    public function __construct(
        public MemberStats $stats,
        public array $distinctions,
        /** The events of the member's calendar */
        public int $favoriteEvents,
        public array $cities,
        public array $places,
        /** Every venue of the calendar, not only the busiest */
        public int $placesCount,
        public array $categories,
        public array $categoryThemes,
    ) {
    }
}
