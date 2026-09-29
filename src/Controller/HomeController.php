<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\App\AppContext;
use App\Controller\Location\DefaultController;
use App\Enum\DateRangePreset;
use App\Form\Type\QuickSearchType;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    private const int METROPOLISES = 5;

    #[Route(path: '/', name: 'app_index', methods: ['GET'])]
    public function index(AppContext $appContext, EventRepository $eventRepository, CityRepository $cityRepository): Response
    {
        // The search goes by GET to the agenda of the member's city, until the city picker points it at another one
        // (assets/js/pages/index.js)
        $memberCity = $appContext->getCity();
        $form = $this->createForm(QuickSearchType::class, ['when' => DateRangePreset::Anytime], [
            'action' => null !== $memberCity ? $this->generateUrl('app_agenda_index', ['location' => $memberCity->getSlug()]) : '',
        ]);

        // The countries with events to come, with their busiest cities: the same cards as the country pages
        $countries = $eventRepository->findUpcomingCountries(DefaultController::VENUES);

        $metropolises = $cityRepository->findMetropolises(self::METROPOLISES);
        // Until the back office flags some: the biggest cities of the visitor's country, else of the first one
        $location = $appContext->getLocation();
        $country = $location?->getCountry() ?? $location?->getCity()?->getCountry() ?? $countries[0][0] ?? null;
        if ([] === $metropolises && null !== $country) {
            $metropolises = $cityRepository->findBiggestOfCountry((string) $country->getSlug(), self::METROPOLISES);
        }

        return $this->render('home/index.html.twig', [
            'search_form' => $form,
            'memberCity' => $memberCity,
            'countries' => $countries,
            'metropolises' => $metropolises,
            'totals' => $eventRepository->getUpcomingTotals(),
        ]);
    }
}
