<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Repository\EventRepository;
use DateTime;
use App\Annotation\ReverseProxy;
use App\App\CityManager;
use App\Entity\Event;
use App\Form\Type\CityAutocompleteType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    const EVENT_PER_CATEGORY = 7;
    /**
     * @var \App\Repository\EventRepository
     */
    private $eventRepository;
    public function __construct(EventRepository $eventRepository)
    {
        $this->eventRepository = $eventRepository;
    }

    /**
     * @Route("/", name="app_main_index")
     * @ReverseProxy(expires="tomorrow")
     *
     * @return Response
     */
    public function index(Request $request, CityManager $cityManager)
    {
        $datas = [
            'from' => new DateTime(),
        ];
        if (($city = $cityManager->getCity()) !== null) {
            $datas += [
                'name' => $city->getFullName(),
                'city' => $city->getSlug(),
            ];
        }

        $stats = $this->eventRepository->getCountryEvents();
        $form = $this->createForm(CityAutocompleteType::class, $datas);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $city = $datas['city'];
            $params = [];

            if (!empty($datas['from'])) {
                $params['from'] = $datas['from']->format('Y-m-d');
            }

            if (!empty($datas['to'])) {
                $params['to'] = $datas['to']->format('Y-m-d');
            }

            return $this->redirectToRoute('app_agenda_agenda', $params + [
                    'location' => $city,
                ]);
        }

        return $this->render('Default/index.html.twig', [
            'autocomplete_form' => $form->createView(),
            'stats' => $stats,
        ]);
    }
}
