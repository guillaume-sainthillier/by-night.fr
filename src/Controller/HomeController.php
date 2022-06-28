<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\App\CityManager;
use App\Form\Type\CityAutocompleteType;
use App\Repository\EventRepository;
use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @ReverseProxy(expires="tomorrow")
     */
    #[Route(path: '/', name: 'app_index', methods: ['GET', 'POST'])]
    public function index(Request $request, CityManager $cityManager, EventRepository $eventRepository): Response
    {
        $datas = [
            'from' => new DateTime(),
        ];
        if (null !== ($city = $cityManager->getCity())) {
            $datas += [
                'name' => $city->getName(),
                'city' => $city->getSlug(),
            ];
        }

        $stats = $eventRepository->getCountryEvents();
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

            return $this->redirectToRoute('app_agenda_index', $params + [
                    'location' => $city,
                ]);
        }

        return $this->render('home/index.html.twig', [
            'autocomplete_form' => $form->createView(),
            'stats' => $stats,
        ]);
    }
}
