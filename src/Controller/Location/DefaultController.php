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
use App\Enum\DateRangePreset;
use App\Form\Type\QuickSearchType;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

final class DefaultController extends BaseController
{
    private const int HIGHLIGHTS = 4;

    /** Also the busiest cities on each country card, here and on the home page: they share the query */
    public const int VENUES = 5;

    /** Below, a country shows its venues instead of its cities */
    private const int MIN_CITIES = 3;

    private const int NEIGHBOURS = 5;

    // Shared by the CDN for visitors only (SharedCacheSubscriber); never by a browser, which would keep it after a login
    #[Cache(maxage: 0, smaxage: 600, public: true, staleWhileRevalidate: 600, staleIfError: 86400)]
    #[Route(path: '/', name: 'app_location_index', methods: ['GET'])]
    public function index(AppContext $appContext, EventRepository $eventRepository): Response
    {
        $location = $appContext->getLocation();

        $cities = [];
        if ($location->isCity()) {
            // The cities around the city
            $neighbours = $eventRepository->findUpcomingCitiesAround($location->getCity(), self::NEIGHBOURS);
        } else {
            // The busiest cities of the country, and the other countries, as the home page lists them
            $cities = $eventRepository->findUpcomingCitiesOfCountry($location->getCountry(), self::VENUES);
            $neighbours = $eventRepository->findUpcomingCountries(self::VENUES, $location->getCountry());
        }

        // The venues, for a city or a country with too few busy cities to rank (Monaco)
        $venues = \count($cities) < self::MIN_CITIES ? $eventRepository->findUpcomingPlaces($location, self::VENUES) : [];

        return $this->render('location/index.html.twig', [
            'search_form' => $this->createForm(QuickSearchType::class, ['when' => DateRangePreset::Anytime], [
                'action' => $this->generateUrl('app_agenda_index', ['location' => $location->getSlug()]),
            ]),
            'location' => $location,
            'highlights' => $eventRepository->findHighlights($location, self::HIGHLIGHTS),
            'cities' => \count($cities) >= self::MIN_CITIES ? $cities : [],
            'venues' => $venues,
            'neighbours' => $neighbours,
        ]);
    }
}
