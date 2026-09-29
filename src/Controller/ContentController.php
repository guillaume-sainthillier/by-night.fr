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
use App\App\Location;
use App\Controller\AbstractController as BaseController;
use App\Repository\EventRepository;
use IntlListFormatter;
use MessageFormatter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContentController extends BaseController
{
    /** The posters of the "how it works" page: real events of the week, the ones members follow the most */
    private const int POSTERS = 4;

    /** The French overseas departments and collectivities, grouped as "l'Outre-Mer" on the "about" page */
    private const array OVERSEAS = ['GP', 'MQ', 'GF', 'RE', 'YT', 'PM', 'BL', 'MF', 'WF', 'PF', 'NC', 'TF'];

    #[Route(path: '/cookie', name: 'app_main_cookie', methods: ['GET'])]
    public function cookie(): Response
    {
        return $this->render('content/cookies.html.twig');
    }

    #[Route(path: '/mentions-legales', name: 'app_legal_mentions', methods: ['GET'])]
    public function legalMentions(): Response
    {
        return $this->render('content/legal-mentions.html.twig');
    }

    #[Route(path: '/a-propos', name: 'app_about', methods: ['GET'])]
    public function about(AppContext $appContext, EventRepository $eventRepository): Response
    {
        // The countries and territories with events to come, the busiest first
        $countries = array_column($eventRepository->getCountryEvents(), 'displayName', 'id');

        return $this->render('content/about.html.twig', [
            'countries' => $countries,
            'countriesSummary' => self::summarizeCountries($countries),
            'location' => $appContext->getLocation(),
        ]);
    }

    #[Route(path: '/en-savoir-plus', name: 'app_plus', methods: ['GET'])]
    public function plus(EventRepository $eventRepository): Response
    {
        return $this->render('content/plus.html.twig', [
            'posters' => $eventRepository->findHighlights(new Location(), self::POSTERS),
            'members' => $eventRepository->getMemberTotals(),
        ]);
    }

    /**
     * The caption of the countries stat card, e.g. "France, Suisse, Monaco, Belgique et 5 territoires d'Outre-Mer".
     *
     * @param array<string, string> $countries display names keyed by ISO code (FR, CH, RE…), the busiest first
     */
    public static function summarizeCountries(array $countries): string
    {
        // The overseas territories become one item that still adds up with the card's count; France leads, the other
        // countries keep their order
        $overseas = array_intersect_key($countries, array_flip(self::OVERSEAS));
        $others = array_diff_key($countries, $overseas);
        if (isset($others['FR'])) {
            $others = ['FR' => $others['FR']] + $others;
        }

        $items = array_values($others);
        if ([] !== $overseas) {
            $items[] = MessageFormatter::formatMessage(
                'fr',
                "{count, plural, =1 {{name}} other {# territoires d''Outre-Mer}}",
                ['count' => \count($overseas), 'name' => array_first($overseas)],
            ) ?: throw new RuntimeException(intl_get_error_message());
        }

        return new IntlListFormatter('fr')->format($items) ?: throw new RuntimeException(intl_get_error_message());
    }
}
