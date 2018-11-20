<?php

namespace App\Controller\City;

use App\Annotation\BrowserCache;
use App\Controller\TBNController as Controller;
use App\Entity\Agenda;
use App\Entity\City;
use App\Search\SearchAgenda;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /**
     * @Cache(expires="+2 hours", smaxage="7200")
     * @Route("/", name="tbn_agenda_index")
     * @BrowserCache(false)
     * @param City $city
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(City $city)
    {
        $em   = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Agenda::class);

        $search    = (new SearchAgenda())->setDu(null);
        $topEvents = $repo->findTopSoiree($city, 1, 7);

        return $this->render('City/Default/index.html.twig', [
            'city'      => $city,
            'topEvents' => $topEvents,
            'nbEvents'  => $repo->findCountWithSearch($city, $search),
        ]);
    }
}
