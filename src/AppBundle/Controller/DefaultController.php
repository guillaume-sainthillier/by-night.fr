<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;
use AppBundle\Configuration\BrowserCache;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="tbn_main_index")
     * @Cache(expires="tomorrow", maxage="86400", smaxage="86400", public=true)
     * @BrowserCache(false)
     */
    public function indexAction()
    {
        $doctrine = $this->getDoctrine();
        $repo = $doctrine->getRepository("AppBundle:Site");
        $sites = $repo->findStats();

        $villes = array_map(function (array $site) {
            return $site['nom'];
        }, $sites);

        return $this->render('Default/index.html.twig', ["sites" => $sites, "villes" => $villes]);
    }
}
