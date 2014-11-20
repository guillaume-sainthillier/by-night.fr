<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Calendrier;
use Symfony\Component\HttpFoundation\JsonResponse;

class CalendrierController extends Controller
{

    public function participerAction(Agenda $agenda, $participer, $interet)
    {
        /**
         * @var TBNUserBundle:User Description
         */
        $user = $this->get('security.context')->getToken()->getUser();

        $em         = $this->getDoctrine()->getManager();
        $calendrier = $em->getRepository("TBNAgendaBundle:Calendrier")->findOneBy(["user" => $user, "agenda" => $agenda]);


        if($calendrier === null)
        {
            $calendrier = new Calendrier;
            $calendrier->setUser($user)->setAgenda($agenda);
        }
        $calendrier->setParticipe($participer)->setInteret($interet);
        
        $em->persist($calendrier);
        $em->flush();

	$repo		= $em->getRepository("TBNAgendaBundle:Agenda");
        $participations = $repo->getCountTendancesParticipation($agenda);
        $interets	= $repo->getCountTendancesInterets($agenda);

	$agenda->setParticipations($participations)->setInterets($interets);
	$em->flush();

        return new JsonResponse([
            "success" => true,
            "participer" => $participer,
            "interet" => $interet
        ]);
    }
}
