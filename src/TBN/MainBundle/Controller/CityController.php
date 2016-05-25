<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use TBN\MainBundle\Entity\Site;

class CityController extends Controller
{
    public function indexAction()
    {
        $doctrine = $this->getDoctrine();
        $repo = $doctrine->getRepository("TBNMainBundle:Site");
        $sites = $repo->findBy([], ["nom" => "ASC"]);
        $villes = array_map(function (Site $site) {
            return $site->getNom();
        }, $sites);

        return $this->render('TBNMainBundle:Ville:list.html.twig', ["sites" => $sites, "villes" => $villes]);
    }
}
