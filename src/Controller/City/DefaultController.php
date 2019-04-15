<?php

namespace App\Controller\City;

use App\Annotation\BrowserCache;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Agenda;
use App\Entity\City;
use App\Search\SearchAgenda;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends BaseController
{
    /**
     * @Cache(expires="+2 hours", smaxage="7200")
     * @Route("/", name="app_agenda_index")
     * @BrowserCache(false)
     *
     * @param City $city
     *
     * @return Response
     */
    public function indexAction(Location $location)
    {
        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        $search    = (new SearchAgenda())->setDu(null);
        $topEvents = $repo->findTopSoiree($location, 1, 7);

        return $this->render('City/Default/index.html.twig', [
            'location'      => $location,
            'topEvents' => $topEvents,
            'nbEvents'  => $repo->findCountWithSearch($location, $search),
        ]);
    }
}
