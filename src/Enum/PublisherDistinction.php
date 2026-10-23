<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Enum;

use App\Stats\PublisherCompletion;
use App\Stats\PublisherStats;
use DateTimeImmutable;

/**
 * The badges a publisher earns on its page (/organisateurs/{slug}), from its events online and its page. Each
 * threshold rewards a few dozen of the ~1,100 publishers (measured on 2026-10-23 on the 2026-09-22 copy): enough to be
 * seen, few enough to mean something.
 */
enum PublisherDistinction: string
{
    /** Its page is complete */
    case CompletePage = 'complete_page';

    /** Published 25 events or more */
    case Prolific = 'prolific';

    /** Had an event in 6 of the last 12 months or more */
    case Regular = 'regular';

    /** Its events got 30 "J'y vais" and favourites or more */
    case CrowdPuller = 'crowd_puller';

    /** Held 10 events or more at the same venue */
    case Resident = 'resident';

    /** Held events in 5 cities or more */
    case OnTour = 'on_tour';

    /** Held 5 free events or more */
    case FreeEntry = 'free_entry';

    /** Has published on By Night for 5 years or more */
    case Pioneer = 'pioneer';

    public const int PROLIFIC_EVENTS = 25;

    public const int REGULAR_MONTHS = 6;

    public const int CROWD_PULLER_AUDIENCE = 30;

    public const int RESIDENT_EVENTS = 10;

    public const int ON_TOUR_CITIES = 5;

    public const int FREE_ENTRY_EVENTS = 5;

    public const int PIONEER_YEARS = 5;

    /**
     * @return list<self> the distinctions the publisher has earned, in the order of the cases
     */
    public static function earnedBy(PublisherStats $stats, PublisherCompletion $completion, DateTimeImmutable $today = new DateTimeImmutable('today')): array
    {
        $earned = [
            self::CompletePage->value => $completion->isComplete(),
            self::Prolific->value => $stats->events >= self::PROLIFIC_EVENTS,
            self::Regular->value => $stats->activeMonths >= self::REGULAR_MONTHS,
            self::CrowdPuller->value => $stats->getAudience() >= self::CROWD_PULLER_AUDIENCE,
            self::Resident->value => $stats->busiestPlaceEvents >= self::RESIDENT_EVENTS,
            self::OnTour->value => $stats->cities >= self::ON_TOUR_CITIES,
            self::FreeEntry->value => $stats->freeEvents >= self::FREE_ENTRY_EVENTS,
            self::Pioneer->value => null !== $stats->firstYear && $stats->firstYear <= (int) $today->format('Y') - self::PIONEER_YEARS,
        ];

        return array_values(array_filter(self::cases(), static fn (self $distinction): bool => $earned[$distinction->value]));
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::CompletePage => 'Page complète',
            self::Prolific => 'Prolifique',
            self::Regular => 'Régulier',
            self::CrowdPuller => 'Rassembleur',
            self::Resident => 'Résident',
            self::OnTour => 'En tournée',
            self::FreeEntry => 'Entrée libre',
            self::Pioneer => 'Pionnier',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CompletePage => 'Logo, présentation, liens, couverture et un événement à venir',
            self::Prolific => \sprintf('%d événements publiés ou plus', self::PROLIFIC_EVENTS),
            self::Regular => \sprintf('Des événements sur %d des 12 derniers mois ou plus', self::REGULAR_MONTHS),
            self::CrowdPuller => \sprintf('%d «\u{a0}J\'y vais\u{a0}» et favoris ou plus sur ses événements', self::CROWD_PULLER_AUDIENCE),
            self::Resident => \sprintf('%d événements ou plus dans un même lieu', self::RESIDENT_EVENTS),
            self::OnTour => \sprintf('Des événements dans %d villes ou plus', self::ON_TOUR_CITIES),
            self::FreeEntry => \sprintf('%d événements gratuits ou plus', self::FREE_ENTRY_EVENTS),
            self::Pioneer => \sprintf('Publie sur By Night depuis %d ans ou plus', self::PIONEER_YEARS),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::CompletePage => 'lucide:badge-check',
            self::Prolific => 'lucide:calendar-plus',
            self::Regular => 'lucide:repeat',
            self::CrowdPuller => 'lucide:users',
            self::Resident => 'lucide:map-pin',
            self::OnTour => 'lucide:map',
            self::FreeEntry => 'lucide:ticket',
            self::Pioneer => 'lucide:shield-check',
        };
    }

    /**
     * @return string a Tabler colour, for a tinted badge (bg-{tone}-lt)
     */
    public function getTone(): string
    {
        return match ($this) {
            self::CompletePage => 'teal',
            self::Prolific => 'primary',
            self::Regular => 'pink',
            self::CrowdPuller => 'purple',
            self::Resident => 'blue',
            self::OnTour => 'green',
            self::FreeEntry => 'green',
            self::Pioneer => 'yellow',
        };
    }
}
