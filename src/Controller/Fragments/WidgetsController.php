<?php

namespace App\Controller\Fragments;

use App\Annotation\ReverseProxy;
use App\Controller\TBNController as BaseController;
use App\Entity\User;
use App\Parser\ProgrammeTVParser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WidgetsController extends BaseController
{
    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/programme-tv", name="app_agenda_programme_tv")
     *
     * @param ProgrammeTVParser $parser
     *
     * @return Response
     */
    public function programmeTVAction(ProgrammeTVParser $parser)
    {
        $programmes = $parser->getProgrammesTV();

        $response = $this->render('City/Hinclude/programme_tv.html.twig', [
            'programmes' => $programmes,
        ]);

        return $response
            ->setExpires(new \DateTime('tomorrow'))
            ->setSharedMaxAge($this->getSecondsUntilTomorrow())
            ->setPublic();
    }

    /**
     * @Route("/top/membres/{page}", name="app_agenda_top_membres", requirements={"page": "\d+"})
     * @ReverseProxy(expires="6 hours")
     *
     * @param int $page
     *
     * @return Response
     */
    public function topMembresAction($page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(User::class);

        $count = $repo->findMembresCount();
        $current = $page * self::WIDGET_ITEM_LIMIT;

        if ($current < $count) {
            $hasNextLink = $this->generateUrl('app_agenda_top_membres', [
                'page' => $page + 1,
            ]);
        } else {
            $hasNextLink = null;
        }

        return $this->render('City/Hinclude/membres.html.twig', [
            'membres' => $repo->findTopMembres($page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current' => $current,
            'count' => $count,
        ]);
    }
}
