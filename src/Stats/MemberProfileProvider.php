<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Stats;

use App\Entity\User;
use App\Enum\MemberDistinction;
use App\Enum\PersonalEventFilter;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;

/**
 * Gathers what a member's profile shows and their distinctions are earned from, wherever they are shown.
 */
final readonly class MemberProfileProvider
{
    /** The busiest venues listed */
    public const int TOP_PLACES = 5;

    /** The busiest categories whose themes are listed */
    public const int TOP_CATEGORIES = 5;

    public function __construct(
        private EventRepository $eventRepository,
        private CommentRepository $commentRepository,
    ) {
    }

    public function get(User $user): MemberProfile
    {
        $cities = $this->eventRepository->findUserCities($user);
        $places = $this->eventRepository->findUserPlaces($user, self::TOP_PLACES);
        $categories = $this->eventRepository->findUserCategories($user);
        $habits = $this->eventRepository->countUserCalendarHabits($user, MemberDistinction::PLANNER_DAYS);

        $stats = new MemberStats(
            activity: new MemberActivity($this->eventRepository->countUserEventsByDay($user)),
            publishedEvents: $this->eventRepository->countByUserAndFilter($user)[PersonalEventFilter::Visible->value],
            comments: $this->commentRepository->countApprovedByUser($user),
            cities: \count($cities),
            busiestPlaceEvents: $places[0]['events'] ?? 0,
            eventsAddedAhead: $habits['addedAhead'],
            freeEvents: $habits['free'],
        );

        return new MemberProfile(
            stats: $stats,
            distinctions: MemberDistinction::earnedBy($user, $stats),
            favoriteEvents: $this->eventRepository->getUserFavoriteEventsCount($user),
            cities: $cities,
            places: $places,
            placesCount: $this->eventRepository->countUserPlaces($user),
            categories: $categories,
            categoryThemes: $this->eventRepository->findUserThemesByCategory($user, array_column(\array_slice($categories, 0, self::TOP_CATEGORIES), 'id')),
        );
    }
}
