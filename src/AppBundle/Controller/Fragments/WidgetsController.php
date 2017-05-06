<?php

namespace AppBundle\Controller\Fragments;

use AppBundle\Entity\Site;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Controller\TBNController as Controller;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Description of MenuDroitController
 *
 * @author guillaume
 */
class WidgetsController extends Controller
{
    const FB_MEMBERS_LIMIT = 100;
    const TWEET_LIMIT = 25;
    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/programme-tv", name="tbn_agenda_programme_tv")
     */
    public function programmeTVAction()
    {
        $parser = $this->get("tbn.programmetv");
        $programmes = $parser->getProgrammesTV();

        $response = $this->render("City/Hinclude/programme_tv.html.twig", [
            "programmes" => $programmes
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic();
    }

    /**
     * @Route("/top/membres/{page}", name="tbn_agenda_top_membres", requirements={"page": "\d+"})
     */
    public function topMembresAction($page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository("AppBundle:User");

        $count = $repo->findMembresCount();
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('tbn_agenda_top_membres', [
                'page' => $page + 1
            ]);
        } else {
            $hasNextLink = null;
        }

        $response = $this->render("City/Hinclude/membres.html.twig", [
            "membres" => $repo->findTopMembres($page, self::WIDGET_ITEM_LIMIT),
            "hasNextLink" => $hasNextLink,
            "current" => $current,
            "count" => $count
        ]);

        list($future, $seconds) = $this->getSecondsUntil(6);

        $response->headers->add([
            'X-No-Browser-Cache' => '1'
        ]);

        return $response
            ->setExpires($future)
            ->setSharedMaxAge($seconds)
            ->setPublic();
    }
}
