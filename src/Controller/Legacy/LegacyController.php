<?php

namespace AppBundle\Controller\Legacy;

use AppBundle\Controller\TBNController as Controller;
use Symfony\Component\Routing\Annotation\Route;

class LegacyController extends Controller
{
    /**
     * @Route("/mentions-legales.html", name="tbn_agenda_mention_legales")
     */
    public function mentionLegalesAction()
    {
        return $this->render('Legacy/mentions.html.twig');
    }

    /**
     * @Route("/a-propos.html", name="tbn_agenda_about")
     */
    public function aboutAction()
    {
        return $this->render('Legacy/about.html.twig');
    }

    /**
     * @Route("/en-savoir-plus.html", name="tbn_agenda_plus")
     */
    public function plusAction()
    {
        return $this->render('Legacy/plus.html.twig');
    }
}
