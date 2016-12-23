<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


use TBN\MainBundle\Entity\Site;

class CityController extends Controller
{
    /**
     * @Cache(expires="tomorrow", maxage="86400", smaxage="86400", public=true)
     */
    public function indexAction()
    {
        $doctrine = $this->getDoctrine();
        $repo = $doctrine->getRepository("TBNMainBundle:Site");
        $sites = $repo->findStats();

        $villes = array_map(function (array $site) {
            return $site['nom'];
        }, $sites);

        return $this->render('TBNMainBundle:Ville:list.html.twig', ["sites" => $sites, "villes" => $villes]);
    }
}
