<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\PersonalSpace;

use App\Controller\AbstractController as BaseController;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The outings of a member, the one who goes out rather than the organizer ("Mes événements"): the events to come they
 * said they go to, soonest first. The past ones stay on their public profile.
 */
final class OutingController extends BaseController
{
    private const int OUTINGS_PER_PAGE = 12;

    #[Route(path: '/mes-sorties', name: 'app_outing_list', methods: ['GET'])]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        $user = $this->getAppUser();

        $outings = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findAllNextEvents($user, true),
            $eventRepository,
            max(1, $request->query->getInt('page', 1)),
            self::OUTINGS_PER_PAGE,
            ['view' => 'events:agenda:list'],
        );

        return $this->render('personal-space/outings.html.twig', [
            'outings' => $outings,
            'pastCount' => max(0, $eventRepository->getUserFavoriteEventsCount($user) - $outings->getNbResults()),
        ]);
    }
}
