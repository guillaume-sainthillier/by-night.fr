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
        $events = $this->createMultipleEagerLoadingPaginator(
            $eventRepository->findUpcomingEvents($location),
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
