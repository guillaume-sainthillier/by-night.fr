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
use Silarhi\LlmsTxtBundle\Model\Section;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContentController extends BaseController
{
    /** The posters of the "how it works" page: real events of the week, the ones members follow the most */
    private const int POSTERS = 4;

    #[Route(path: '/cookie', name: 'app_main_cookie', options: ['llms_txt' => new LlmsTxtEntry(title: 'Politique de cookies', description: 'Les cookies utilisés par By Night, leur rôle, leur durée et comment accepter ou refuser ceux qui demandent votre accord.', section: Section::OPTIONAL)], methods: ['GET'])]
    public function cookie(): Response
    {
        return $this->render('content/cookies.html.twig');
    }

    #[Route(path: '/mentions-legales', name: 'app_legal_mentions', options: ['llms_txt' => new LlmsTxtEntry(title: 'Mentions légales', description: 'Mentions légales de By Night : éditeur, hébergeur, règles de publication, signalement de contenus et protection de vos données personnelles.', section: Section::OPTIONAL)], methods: ['GET'])]
    public function legalMentions(): Response
    {
        return $this->render('content/legal-mentions.html.twig');
    }

    #[Route(path: '/a-propos', name: 'app_about', options: ['llms_txt' => new LlmsTxtEntry(title: 'À propos de By Night', description: 'By Night est votre guide de sorties en France. Depuis 2013, nous référençons concerts, spectacles, expos et événements pour vous aider à trouver votre prochaine sortie.')], methods: ['GET'])]
    public function about(AppContext $appContext, EventRepository $eventRepository): Response
    {
        return $this->render('content/about.html.twig', [
            // The countries and territories with events to come, the busiest first
            'countries' => array_column($eventRepository->getCountryEvents(), 'displayName', 'id'),
            'location' => $appContext->getLocation(),
        ]);
    }

    #[Route(path: '/en-savoir-plus', name: 'app_plus', options: ['llms_txt' => new LlmsTxtEntry(title: 'Comment publier un événement sur By Night', description: 'Publiez votre événement gratuitement sur By Night en 4 étapes simples : configurez, communiquez, contrôlez et suivez vos événements.')], methods: ['GET'])]
    public function plus(EventRepository $eventRepository): Response
    {
        return $this->render('content/plus.html.twig', [
            'posters' => $eventRepository->findHighlights(new Location(), self::POSTERS),
            'members' => $eventRepository->getMemberTotals(),
        ]);
    }
}
