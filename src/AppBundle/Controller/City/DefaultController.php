<?php

namespace AppBundle\Controller\City;

use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use FOS\HttpCacheBundle\Configuration\Tag;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use AppBundle\Configuration\BrowserCache;
use TBN\MainBundle\Controller\TBNController as Controller;

use TBN\MainBundle\Entity\Site;
use TBN\AgendaBundle\Search\SearchAgenda;

class DefaultController extends Controller
{
    /**
     * @Cache(expires="+2 hours", smaxage="7200")
     * @Route("/", name="tbn_agenda_index")
     * @BrowserCache(false)
     */
    public function indexAction(Site $site)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("TBNAgendaBundle:Agenda");

        $search = (new SearchAgenda)->setDu(null);
        $topEvents = $repo->findTopSoiree($site, 1, 7);

        return $this->render('TBNAgendaBundle:Agenda:index.html.twig', [
            'site' => $site,
            'topEvents' => $topEvents,
            'nbEvents' => $repo->findCountWithSearch($site, $search)
        ]);
    }
}
