<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Legacy;

use App\Annotation\ReverseProxy;
use App\Controller\TBNController as BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @ReverseProxy(expires="1 year")
 */
class LegacyController extends BaseController
{
    /**
     * @Route("/cookie", name="app_main_cookie", methods={"GET"})
     */
    public function cookie(): Response
    {
        return $this->render('Legacy/cookies.html.twig');
    }

    /**
     * @Route("/mentions-legales", name="app_mentions_legales", methods={"GET"})
     */
    public function mentionLegales(): Response
    {
        return $this->render('Legacy/mentions.html.twig');
    }

    /**
     * @Route("/a-propos", name="app_about", methods={"GET"})
     */
    public function about(): Response
    {
        return $this->render('Legacy/about.html.twig');
    }

    /**
     * @Route("/en-savoir-plus", name="app_plus", methods={"GET"})
     */
    public function plus(): Response
    {
        return $this->render('Legacy/plus.html.twig');
    }
}
