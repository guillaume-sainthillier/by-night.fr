<?php

namespace App\Controller\Legacy;

use App\Controller\TBNController as BaseController;
use Symfony\Component\Routing\Annotation\Route;

class LegacyController extends BaseController
{
    /**
     * @Route("/cookie", name="app_main_cookie")
     */
    public function cookie()
    {
        return $this->render('Legacy/cookies.html.twig');
    }

    /**
     * @Route("/mentions-legales", name="app_agenda_mention_legales")
     */
    public function mentionLegales()
    {
        return $this->render('Legacy/mentions.html.twig');
    }

    /**
     * @Route("/a-propos", name="app_agenda_about")
     */
    public function about()
    {
        return $this->render('Legacy/about.html.twig');
    }

    /**
     * @Route("/en-savoir-plus", name="app_agenda_plus")
     */
    public function plus()
    {
        return $this->render('Legacy/plus.html.twig');
    }
}
