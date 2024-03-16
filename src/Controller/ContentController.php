<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Annotation\ReverseProxy;
use App\Controller\AbstractController as BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[ReverseProxy(expires: '1 year')]
class ContentController extends BaseController
{
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
    public function about(): Response
    {
        return $this->render('content/about.html.twig');
    }

    #[Route(path: '/en-savoir-plus', name: 'app_plus', methods: ['GET'])]
    public function plus(): Response
    {
        return $this->render('content/plus.html.twig');
    }
}
