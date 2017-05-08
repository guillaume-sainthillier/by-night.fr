<?php

namespace AppBundle\Controller\Old\City;

use AppBundle\Controller\TBNController as Controller;
use Symfony\Component\Routing\Annotation\Route;

class WigetsController extends Controller
{
    /**
     * @Route("/{city}/programme-tv", name="tbn_agenda_programme_tv_old", requirements={"city": ".+"})
     */
    public function programmeTVAction() {
        return $this->redirectToRoute("tbn_agenda_programme_tv");
    }
}
