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
use App\Manager\UserRedirectManager;
use App\Repository\EventRepository;
use App\Stats\MemberProfileProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/membres')]
final class UserController extends BaseController
{
    public const int EVENTS_PER_PAGE = 6;

    private const int TOP_CITIES = 3;

    #[Route(path: '/{slug<%patterns.slug%>}--{id<%patterns.id%>}', name: 'app_user_index', methods: ['GET'])]
    #[Route(path: '/{username<%patterns.slug%>}', name: 'app_user_index_old', methods: ['GET'])]
    public function index(UserRedirectManager $userRedirectManager, EventRepository $eventRepository, MemberProfileProvider $memberProfileProvider, ?int $id = null, ?string $slug = null, ?string $username = null): Response
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

        $profile = $memberProfileProvider->get($user);

        return $this->render('user/index.html.twig', [
            'user' => $user,
            'nextEvents' => $nextEvents,
            'previousEvents' => $previousEvents,
            'favoriteEventsCount' => $profile->favoriteEvents,
            'publishedEventsCount' => $profile->stats->publishedEvents,
            'activity' => $profile->stats->activity,
            'distinctions' => $profile->distinctions,
            'cities' => \array_slice($profile->cities, 0, self::TOP_CITIES),
            'citiesCount' => \count($profile->cities),
            'cityEventsCount' => array_sum(array_column($profile->cities, 'events')),
            'places' => $profile->places,
            'placesCount' => $profile->placesCount,
            'categories' => \array_slice($profile->categories, 0, MemberProfileProvider::TOP_CATEGORIES),
            'categoriesCount' => \count($profile->categories),
            'categoryEventsCount' => array_sum(array_column($profile->categories, 'events')),
            'categoryThemes' => $profile->categoryThemes,
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
