<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\AbstractController as BaseController;
use App\Form\Type\SimpleEventSearchType;
use App\Repository\EventRepository;
use DateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends BaseController
{
    /**
     * @ReverseProxy(expires="tomorrow")
     */
    #[Route(path: '/', name: 'app_location_index', methods: ['GET'])]
    public function index(Location $location, EventRepository $eventRepository): Response
    {
        $datas = [
            'from' => new DateTime(),
        ];
        $events = $this->createQueryBuilderPaginator(
            $eventRepository->findUpcomingEventsQueryBuilder($location),
            1,
            7
        );
        $form = $this->createForm(SimpleEventSearchType::class, $datas);

        return $this->render('location/index.html.twig', [
            'location' => $location,
            'events' => $events,
            'form' => $form->createView(),
        ]);
    }
}
