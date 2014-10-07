<?php

namespace TBN\AdministrationBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class HistoriqueController extends Controller
{
    public function listAction()
    {
        $repo = $this->getDoctrine()->getRepository("TBNMajDataBundle:HistoriqueMaj");
        $historiques = $repo->findBy([], ["id" => "DESC"], 100);

        return $this->render('TBNAdministrationBundle:Historique:list.html.twig', [
            'historiques' => $historiques
        ]);
    }
}
