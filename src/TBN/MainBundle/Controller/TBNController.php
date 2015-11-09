<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use TBN\AgendaBundle\Entity\Agenda;

class TBNController extends Controller
{    
    protected function getFBStatsEvent(Agenda $soiree) {
        $id = $soiree->getFacebookEventId();
        if ($id !== null) {
            $dureeCache = 12;
            $dateModification = $soiree->getDateModification();
            $today = new \DateTime;
            $dateModification->modify("+" . $dureeCache . " hours");
            if ($dateModification <= $today || $soiree->getFbParticipations() === null || $soiree->getFbInterets() === null) {
                $api    = $this->get("tbn.social.facebook_admin");
                $retour = $api->getEventStats($id);
                $cache  = $this->get("winzou_cache");
                $cache->save("fb.stats." . $id, $retour["membres"]);

                $soiree->setFbInterets($retour["nbInterets"]);
                $soiree->setFbParticipations($retour["nbParticipations"]);
                $soiree->preDateModification();

                $em = $this->getDoctrine()->getManager();
                $em->persist($soiree);
                $em->flush();
            }
        }
    }
    
    protected function getFBMembres(Agenda $soiree, $page, $offset)
    {
        $membres = [];
        if ($soiree->getFacebookEventId()) {
            $key = "fb.stats." . $soiree->getFacebookEventId();
            $cache = $this->get("winzou_cache");
            if ($cache->contains($key)) {
                $membres = $cache->fetch($key);
            }
        }

        return array_slice($membres, ($page - 1) * $offset, $offset);
    }
    
    protected function getRepo($name)
    {
        $em = $this->getDoctrine()->getManager();
        return $em->getRepository($name);
    }
}
