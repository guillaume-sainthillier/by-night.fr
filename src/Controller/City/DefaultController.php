<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Form\Type\SimpleEventSearchType;
use App\Repository\EventRepository;
use DateTime;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends BaseController
{
    /**
     * @Route("/", name="app_agenda_index", methods={"GET"})
     * @ReverseProxy(expires="tomorrow")
     */
    public function index(Location $location, PaginatorInterface $paginator, EventRepository $eventRepository): Response
    {
        $datas = [
            'from' => new DateTime(),
        ];

        $query = $eventRepository->findUpcomingEvents($location);
        $events = $paginator->paginate($query, 1, 7);

        $form = $this->createForm(SimpleEventSearchType::class, $datas);

        return $this->render('City/Default/index.html.twig', [
            'location' => $location,
            'events' => $events,
            'form' => $form->createView(),
        ]);
    }
}
