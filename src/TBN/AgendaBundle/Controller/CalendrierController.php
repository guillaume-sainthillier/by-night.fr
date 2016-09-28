<?php

namespace TBN\AgendaBundle\Controller;

use TBN\MainBundle\Controller\TBNController as Controller;
use TBN\AgendaBundle\Entity\Agenda;
use TBN\AgendaBundle\Entity\Calendrier;
use TBN\UserBundle\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;

class CalendrierController extends Controller
{

    private function updateFBEvent(Agenda $agenda, User $user, Calendrier $calendrier)
    {
        if ($agenda->getFacebookEventId() && $user->getInfo() && $user->getInfo()->getFacebookAccessToken()) {
            $key = 'users.' . $user->getId() . '.stats.' . $agenda->getId();
            $cache = $this->get('memory_cache');
            $api = $this->get('tbn.social.facebook_admin');
            $api->updateEventStatut(
                $agenda->getFacebookEventId(),
                $user->getInfo()->getFacebookAccessToken(),
                $calendrier->getParticipe()
            );

            $datas = [
                'participer' => $calendrier->getParticipe(),
                'interet' => $calendrier->getInteret()
            ];

            $cache->save($key, $datas);
        }
    }

    public function participerAction(Agenda $agenda, $participer, $interet)
    {
        /**
         * @var \TBN\UserBundle\Entity\User Description
         */
        $user = $this->get('security.token_storage')->getToken()->getUser();

        $em = $this->getDoctrine()->getManager();
        $calendrier = $em->getRepository("TBNAgendaBundle:Calendrier")->findOneBy(["user" => $user, "agenda" => $agenda]);

        if ($calendrier === null) {
            $calendrier = new Calendrier;
            $calendrier->setUser($user)->setAgenda($agenda);
        }
        $calendrier->setParticipe($participer)->setInteret($interet);
        $this->updateFBEvent($agenda, $user, $calendrier);

        $em->persist($calendrier);
        $em->flush();

        $repo = $em->getRepository("TBNAgendaBundle:Agenda");
        $participations = $repo->getCountTendancesParticipation($agenda);
        $interets = $repo->getCountTendancesInterets($agenda);

        $agenda->setParticipations($participations)->setInterets($interets);
        $em->flush();

        return new JsonResponse([
            "success" => true,
            "participer" => $participer,
            "interet" => $interet
        ]);
    }
}
