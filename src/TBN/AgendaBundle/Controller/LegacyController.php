<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;

class LegacyController extends Controller
{
    public function mentionLegalesAction()
    {
        return $this->render('TBNAgendaBundle:Legacy:mentions.html.twig');
    }

    public function aboutAction()
    {
        return $this->render('TBNAgendaBundle:Legacy:about.html.twig');
    }

    public function plusAction()
    {
        return $this->render('TBNAgendaBundle:Legacy:plus.html.twig');
    }
}
