<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\User;

use App\Controller\AbstractController as BaseController;
use App\Enum\MemberDistinction;
use App\Enum\PersonalEventFilter;
use App\Manager\UserRedirectManager;
use App\Repository\CommentRepository;
use App\Repository\EventRepository;
use App\Stats\MemberActivity;
use App\Stats\MemberStats;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/membres')]
final class UserController extends BaseController
{
    public const int EVENTS_PER_PAGE = 6;

    private const int TOP_CITIES = 3;

    private const int TOP_PLACES = 5;

    private const int TOP_CATEGORIES = 5;

    #[Route(path: '/{slug<%patterns.slug%>}--{id<%patterns.id%>}', name: 'app_user_index', methods: ['GET'])]
    #[Route(path: '/{username<%patterns.slug%>}', name: 'app_user_index_old', methods: ['GET'])]
    public function index(UserRedirectManager $userRedirectManager, EventRepository $eventRepository, CommentRepository $commentRepository, ?int $id = null, ?string $slug = null, ?string $username = null): Response
    {
        $user = $userRedirectManager->getUser($id, $slug, $username, 'app_user_index');

        // Create paginators for next and previous events (first page)
        $nextEvents = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findAllNextEvents($user, true),
            $eventRepository,
            1,
            self::EVENTS_PER_PAGE,
            ['view' => 'events:user:list'],
        );

        $previousEvents = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findAllNextEvents($user, false),
            $eventRepository,
            1,
            self::EVENTS_PER_PAGE,
            ['view' => 'events:user:list'],
        );

        $activity = new MemberActivity($eventRepository->countUserEventsByDay($user));
        $publishedEventsCount = $eventRepository->countByUserAndFilter($user)[PersonalEventFilter::Visible->value];
        $cities = $eventRepository->findUserCities($user);
        $places = $eventRepository->findUserPlaces($user, self::TOP_PLACES);
        $categories = $eventRepository->findUserCategories($user);
        $topCategories = \array_slice($categories, 0, self::TOP_CATEGORIES);
        $habits = $eventRepository->countUserCalendarHabits($user, MemberDistinction::PLANNER_DAYS);

        $stats = new MemberStats(
            activity: $activity,
            publishedEvents: $publishedEventsCount,
            comments: $commentRepository->countApprovedByUser($user),
            cities: \count($cities),
            busiestPlaceEvents: $places[0]['events'] ?? 0,
            eventsAddedAhead: $habits['addedAhead'],
            freeEvents: $habits['free'],
        );

        return $this->render('user/index.html.twig', [
            'user' => $user,
            'nextEvents' => $nextEvents,
            'previousEvents' => $previousEvents,
            'favoriteEventsCount' => $eventRepository->getUserFavoriteEventsCount($user),
            'publishedEventsCount' => $publishedEventsCount,
            'activity' => $activity,
            'distinctions' => MemberDistinction::earnedBy($user, $stats),
            'cities' => \array_slice($cities, 0, self::TOP_CITIES),
            'citiesCount' => \count($cities),
            'cityEventsCount' => array_sum(array_column($cities, 'events')),
            'places' => $places,
            'placesCount' => $eventRepository->countUserPlaces($user),
            'categories' => $topCategories,
            'categoriesCount' => \count($categories),
            'categoryEventsCount' => array_sum(array_column($categories, 'events')),
            'categoryThemes' => $eventRepository->findUserThemesByCategory($user, array_column($topCategories, 'id')),
        ]);
    }

    #[Route(path: '/{slug<%patterns.slug%>}--{id<%patterns.id%>}/next/{page<%patterns.page%>}', name: 'app_user_events_next', methods: ['GET'])]
    public function nextEventsList(UserRedirectManager $userRedirectManager, EventRepository $eventRepository, int $page, ?int $id = null, ?string $slug = null): Response
    {
        $user = $userRedirectManager->getUser($id, $slug, null, 'app_user_events_next');

        $nextEvents = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findAllNextEvents($user, true),
            $eventRepository,
            $page,
            self::EVENTS_PER_PAGE,
            ['view' => 'events:user:list'],
        );

        return $this->render('user/events_list.html.twig', [
            'events' => $nextEvents,
            'user' => $user,
            'isNext' => true,
        ]);
    }

    #[Route(path: '/{slug<%patterns.slug%>}--{id<%patterns.id%>}/previous/{page<%patterns.page%>}', name: 'app_user_events_previous', methods: ['GET'])]
    public function previousEventsList(UserRedirectManager $userRedirectManager, EventRepository $eventRepository, int $page, ?int $id = null, ?string $slug = null): Response
    {
        $user = $userRedirectManager->getUser($id, $slug, null, 'app_user_events_previous');

        $previousEvents = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findAllNextEvents($user, false),
            $eventRepository,
            $page,
            self::EVENTS_PER_PAGE,
            ['view' => 'events:user:list'],
        );

        return $this->render('user/events_list.html.twig', [
            'events' => $previousEvents,
            'user' => $user,
            'isNext' => false,
        ]);
    }
}
