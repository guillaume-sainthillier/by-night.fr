<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

use App\Entity\User;
use App\Stats\MemberStats;
use DateTimeImmutable;

/**
 * The badges a member earns on their profile (/membres/{slug}--{id}), from what their calendar and their account say.
 * Each threshold rewards a few dozen of the ~1,300 members with a calendar (measured on 2026-09-26): enough to be seen,
 * few enough to mean something.
 */
enum MemberDistinction: string
{
    /** Published 10 events or more */
    case Organizer = 'organizer';

    /** Wrote 5 approved comments or more */
    case Commenter = 'commenter';

    /** Has had an account for 10 years or more */
    case Veteran = 'veteran';

    /** Went out in 3 different years or more */
    case Regular = 'regular';

    /** Half of their outings at least start on a Friday or a Saturday, out of 10 or more */
    case NightOwl = 'night_owl';

    /** Went out 10 times or more at the same venue */
    case VenueRegular = 'venue_regular';

    /** Went out in 5 cities or more */
    case Explorer = 'explorer';

    /** Added half of their outings at least 14 days or more before they started, out of 10 or more */
    case Planner = 'planner';

    /** Half of their outings at least were free, out of 10 or more */
    case BargainHunter = 'bargain_hunter';

    public const int ORGANIZER_EVENTS = 10;

    public const int COMMENTER_COMMENTS = 5;

    public const int VETERAN_YEARS = 10;

    public const int REGULAR_YEARS = 3;

    public const int VENUE_REGULAR_EVENTS = 10;

    public const int EXPLORER_CITIES = 5;

    public const int PLANNER_DAYS = 14;

    /**
     * The fewest outings that tell a habit from chance, for the distinctions about a share of them: two Fridays out
     * of two are not a night owl yet.
     */
    public const int HABIT_MIN_EVENTS = 10;

    /**
     * @return list<self> the distinctions the member has earned, in the order of the cases
     */
    public static function earnedBy(User $user, MemberStats $stats, DateTimeImmutable $today = new DateTimeImmutable('today')): array
    {
        $total = $stats->activity->getTotal();
        $byWeekday = $stats->activity->getByWeekday();
        $createdAt = $user->getCreatedAt();
        // "At least half of the outings", on enough of them to be a habit
        $isHabit = static fn (int $count): bool => $total >= self::HABIT_MIN_EVENTS && 2 * $count >= $total;

        $earned = [
            self::Organizer->value => $stats->publishedEvents >= self::ORGANIZER_EVENTS,
            self::Commenter->value => $stats->comments >= self::COMMENTER_COMMENTS,
            self::Veteran->value => null !== $createdAt && $createdAt <= $today->modify(\sprintf('-%d years', self::VETERAN_YEARS)),
            self::Regular->value => \count($stats->activity->getYears()) >= self::REGULAR_YEARS,
            // Friday and Saturday are 2 days out of 7: half of the outings is a clear lean
            self::NightOwl->value => $isHabit($byWeekday[5] + $byWeekday[6]),
            self::VenueRegular->value => $stats->busiestPlaceEvents >= self::VENUE_REGULAR_EVENTS,
            self::Explorer->value => $stats->cities >= self::EXPLORER_CITIES,
            self::Planner->value => $isHabit($stats->eventsAddedAhead),
            self::BargainHunter->value => $isHabit($stats->freeEvents),
        ];

        return array_values(array_filter(self::cases(), static fn (self $distinction): bool => $earned[$distinction->value]));
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Organizer => 'Organisateur',
            self::Commenter => 'Plume',
            self::Veteran => \sprintf('%d+ ans', self::VETERAN_YEARS),
            self::Regular => 'Fidèle',
            self::NightOwl => 'Noctambule',
            self::VenueRegular => 'Habitué',
            self::Explorer => 'Explorateur',
            self::Planner => 'Prévoyant',
            self::BargainHunter => 'Bon plan',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Organizer => \sprintf('%d événements publiés ou plus', self::ORGANIZER_EVENTS),
            self::Commenter => \sprintf('%d commentaires ou plus', self::COMMENTER_COMMENTS),
            self::Veteran => \sprintf('Membre de By Night depuis %d ans ou plus', self::VETERAN_YEARS),
            self::Regular => \sprintf('Des sorties sur %d années différentes ou plus', self::REGULAR_YEARS),
            self::NightOwl => 'Sort surtout le vendredi et le samedi',
            self::VenueRegular => \sprintf('%d sorties ou plus dans un même lieu', self::VENUE_REGULAR_EVENTS),
            self::Explorer => \sprintf('Des sorties dans %d villes ou plus', self::EXPLORER_CITIES),
            self::Planner => \sprintf("Prévoit la plupart de ses sorties %d jours à l'avance ou plus", self::PLANNER_DAYS),
            self::BargainHunter => 'La plupart de ses sorties sont gratuites',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Organizer => 'lucide:calendar-plus',
            self::Commenter => 'lucide:message-circle',
            self::Veteran => 'lucide:shield-check',
            self::Regular => 'lucide:heart',
            self::NightOwl => 'lucide:moon',
            self::VenueRegular => 'lucide:map-pin',
            self::Explorer => 'lucide:map',
            self::Planner => 'lucide:calendar-clock',
            self::BargainHunter => 'lucide:ticket',
        };
    }

    /**
     * @return string a Tabler colour, for a tinted badge (bg-{tone}-lt)
     */
    public function getTone(): string
    {
        return match ($this) {
            self::Organizer => 'primary',
            self::Commenter => 'purple',
            self::Veteran => 'yellow',
            self::Regular => 'pink',
            self::NightOwl => 'teal',
            self::VenueRegular => 'blue',
            self::Explorer => 'green',
            self::Planner => 'secondary',
            self::BargainHunter => 'green',
        };
    }
}
