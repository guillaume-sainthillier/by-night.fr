<?php

namespace App\Controller\Legacy;

use App\Controller\TBNController as BaseController;
use Symfony\Component\Routing\Annotation\Route;

class LegacyController extends BaseController
{
    /**
     * @Route("/mentions-legales", name="app_agenda_mention_legales")
     */
    public function mentionLegalesAction()
    {
        return $this->render('Legacy/mentions.html.twig');
    }

    /**
     * @Route("/a-propos", name="app_agenda_about")
     */
    public function aboutAction()
    {
        return $this->render('Legacy/about.html.twig');
    }

    /**
     * @Route("/en-savoir-plus", name="app_agenda_plus")
     */
    public function plusAction()
    {
        return $this->render('Legacy/plus.html.twig');
    }
}
