<?php

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Search\SearchEvent;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends BaseController
{
    /**
     * @Route("/", name="app_agenda_index")
     * @ReverseProxy(expires="+2 hours")
     */
    public function indexAction(Location $location)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Event::class);

        $search = (new SearchEvent())->setDu(null);
        $topEvents = $repo->findTopSoiree($location, 1, 7);

        return $this->render('City/Default/index.html.twig', [
            'location' => $location,
            'topEvents' => $topEvents,
            'nbEvents' => $repo->findCountWithSearch($location, $search),
        ]);
    }
}
