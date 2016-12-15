<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use TBN\AgendaBundle\Entity\Agenda;

class TBNController extends Controller
{
    private static $CACHE_TTL = 43200; //12 * 3600

    protected function getSecondsUntilTomorrow() {
        $minuit = strtotime('tomorrow 00:00:00');

        return $minuit - time();
    }
    protected function getFBStatsEvent(Agenda $soiree)
    {
        $stats = [];
        $id = $soiree->getFacebookEventId();
        if ($id) {
            $key = 'fb.stats.' . $id;
            $cache = $this->get("memory_cache");
            if (!$cache->contains($key)) {
                $api = $this->get("tbn.social.facebook_admin");
                $retour = $api->getEventStats($id);

                $cache->save($key, $retour["membres"], self::$CACHE_TTL);
                $soiree->setFbInterets($retour["nbInterets"]);
                $soiree->setFbParticipations($retour["nbParticipations"]);

                try {
                    $this->get('tbn.event_handler')->updateImage($soiree, $retour['image']);
                }catch(Exception $e) {
                    $this->get('logger')->critical($e);
                }

                $em = $this->getDoctrine()->getManager();
                $em->persist($soiree);
                $em->flush();
            }
            $stats = $cache->fetch($key);
        }

        return $stats;
    }

    protected function getFBMembres(Agenda $soiree, $page, $offset)
    {
        $membres = $this->getFBStatsEvent($soiree) ?: array();

        return array_slice($membres, ($page - 1) * $offset, $offset);
    }

    protected function getRepo($name)
    {
        $em = $this->getDoctrine()->getManager();
        return $em->getRepository($name);
    }
}
