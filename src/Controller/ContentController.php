<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\App\CityManager;
use App\Controller\AbstractController as BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContentController extends BaseController
{
    #[Route(path: '/cookie', name: 'app_main_cookie', methods: ['GET'])]
    public function cookie(CityManager $cityManager): Response
    {
        return $this->render('content/cookies.html.twig', [
            'headerCity' => $cityManager->getCity(),
        ]);
    }

    #[Route(path: '/mentions-legales', name: 'app_legal_mentions', methods: ['GET'])]
    public function legalMentions(CityManager $cityManager): Response
    {
        return $this->render('content/legal-mentions.html.twig', [
            'headerCity' => $cityManager->getCity(),
        ]);
    }

    #[Route(path: '/a-propos', name: 'app_about', methods: ['GET'])]
    public function about(CityManager $cityManager): Response
    {
        return $this->render('content/about.html.twig', [
            'headerCity' => $cityManager->getCity(),
        ]);
    }

    #[Route(path: '/en-savoir-plus', name: 'app_plus', methods: ['GET'])]
    public function plus(CityManager $cityManager): Response
    {
        return $this->render('content/plus.html.twig', [
            'headerCity' => $cityManager->getCity(),
        ]);
    }
}
