<?php

namespace AppBundle\Controller\City;

use AppBundle\Entity\City;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Configuration\BrowserCache;
use AppBundle\Controller\TBNController as Controller;
use AppBundle\Search\SearchAgenda;

class DefaultController extends Controller
{
    /**
     * @Cache(expires="+2 hours", smaxage="7200")
     * @Route("/", name="tbn_agenda_index")
     * @BrowserCache(false)
     */
    public function indexAction(City $city)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("AppBundle:Agenda");

        $search = (new SearchAgenda)->setDu(null);
        $topEvents = $repo->findTopSoiree($city, 1, 7);

        return $this->render('City/Default/index.html.twig', [
            'city' => $city,
            'topEvents' => $topEvents,
            'nbEvents' => $repo->findCountWithSearch($city, $search)
        ]);
    }
}
