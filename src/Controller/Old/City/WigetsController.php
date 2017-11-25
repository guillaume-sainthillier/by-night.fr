<?php

namespace AppBundle\Controller\Old\City;

use AppBundle\Controller\TBNController as Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WigetsController extends Controller
{
    /**
     * @Route("/{city}/programme-tv", name="tbn_agenda_programme_tv_old", requirements={"city": ".+"})
     */
    public function programmeTVAction()
    {
        return $this->redirectToRoute('tbn_agenda_programme_tv', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @Route("/{city}/top/membres/{page}", name="tbn_agenda_top_membres_old", requirements={"city": ".+", "page": "\d+"})
     */
    public function topMembresAction($page = 1)
    {
        $page = \max(1, $page);

        return $this->redirectToRoute('tbn_agenda_top_membres', ['page' => $page], Response::HTTP_MOVED_PERMANENTLY);
    }
}
