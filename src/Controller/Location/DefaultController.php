<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\App\AppContext;
use App\Controller\AbstractController as BaseController;
use App\Form\Type\SimpleEventSearchType;
use App\Repository\EventRepository;
use DateTimeImmutable;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends BaseController
{
    #[Route(path: '/', name: 'app_location_index', methods: ['GET'])]
    public function index(AppContext $appContext, EventRepository $eventRepository): Response
    {
        $location = $appContext->getLocation();

        $data = [
            'from' => new DateTimeImmutable('now'),
        ];
        $form = $this->createForm(SimpleEventSearchType::class, $data);
        // Tree walkers: the default output walkers wrap the query in derived tables that
        // MySQL materializes (every column of every upcoming event) instead of reading
        // event_upcoming_idx; with them, the Paris page took ~1.5 s instead of ~0.25 s.
        // fetchJoinCollection stays on although no collection is joined: sorting the ids
        // from the index, then loading 8 rows, beats sorting the full rows (~0.3 s vs 0.6 s).
        $events = $this->createMultipleEagerLoadingPaginatorFromAdapter(
            new QueryAdapter($eventRepository->findUpcomingEvents($location), useOutputWalkers: false),
            $eventRepository,
            1,
            8,
            ['view' => 'events:location:index']
        );

        return $this->render('location/index.html.twig', [
            'location' => $location,
            'events' => $events,
            'form' => $form,
        ]);
    }
}
