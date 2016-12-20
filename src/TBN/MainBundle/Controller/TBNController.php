<?php

namespace TBN\MainBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Config\Definition\Exception\Exception;
use TBN\AgendaBundle\Entity\Agenda;

class TBNController extends Controller
{
    protected function getSecondsUntilTomorrow() {
        $minuit = strtotime('tomorrow 00:00:00');

        return $minuit - time();
    }

    protected function getSecondsUntil($hours) {
        $time = time();
        $now = new \DateTime();
        $minutes = $now->format('i');
        $secondes = $now->format('s');

        $string = $hours == 1 ? "+1 hour" : sprintf("+%d hours", $hours);
        $now->modify($string);

        if ($minutes > 0) {
            $now->modify('-'.$minutes.' minutes');
        }

        if ($secondes > 0) {
            $now->modify('-'.$secondes.' seconds');
        }

        return [$now, $now->getTimestamp() - $time];
    }

    protected function getRepo($name)
    {
        $em = $this->getDoctrine()->getManager();
        return $em->getRepository($name);
    }
}
