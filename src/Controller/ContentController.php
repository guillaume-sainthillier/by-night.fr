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
use App\Stats\AdvertiserFiguresProvider;
use Silarhi\LlmsTxtBundle\Model\Section;
use Silarhi\LlmsTxtBundle\Routing\LlmsTxtEntry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
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

    #[Route(path: '/confidentialite', name: 'app_privacy_policy', options: ['llms_txt' => new LlmsTxtEntry(title: 'Politique de confidentialité', description: 'Les données personnelles que By Night collecte, dont celles reçues de Google, Facebook et X à la connexion, leur usage, leur durée de conservation et vos droits.', section: Section::OPTIONAL)], methods: ['GET'])]
    public function privacyPolicy(): Response
    {
        return $this->render('content/privacy-policy.html.twig');
    }

    #[Route(path: '/conditions-generales-d-utilisation', name: 'app_terms', options: ['llms_txt' => new LlmsTxtEntry(title: "Conditions générales d'utilisation", description: 'Les règles du compte By Night, des événements, images et commentaires publiés par les membres, de leur modération et du signalement des contenus illicites.', section: Section::OPTIONAL)], methods: ['GET'])]
    public function terms(): Response
    {
        return $this->render('content/terms.html.twig');
    }

    #[Route(path: '/transparence', name: 'app_transparency', options: ['llms_txt' => new LlmsTxtEntry(title: 'Transparence', description: 'Qui édite By Night, comment le site est financé (publicité, liens affiliés vers des billetteries), comment les événements et les offres de billets sont classés et comment les liens rémunérés sont signalés.', section: Section::OPTIONAL)], methods: ['GET'])]
    public function transparency(): Response
    {
        return $this->render('content/transparency.html.twig');
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

    /** A day in Cloudflare, as long as its figures are kept (AdvertiserFiguresProvider) */
    #[Cache(maxage: 0, smaxage: 86400, public: true, staleWhileRevalidate: 86400, staleIfError: 86400)]
    #[Route(path: '/annonceurs', name: 'app_advertisers', options: ['llms_txt' => new LlmsTxtEntry(title: 'Annonceurs et partenaires', description: "Organisateurs, salles, billetteries et marques : les chiffres de By Night, les formats de partenariat proposés et leur signalement, les solutions gratuites (publication par un compte membre, import d'un agenda) et ce que By Night ne fait pas.")], methods: ['GET'])]
    public function advertisers(AdvertiserFiguresProvider $figures): Response
    {
        return $this->render('content/advertisers.html.twig', [
            // Null when the database or the cache is down: the page hides its figures
            'figures' => $figures->get(),
        ]);
    }
}
