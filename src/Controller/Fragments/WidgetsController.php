<?php

namespace App\Controller\Fragments;

use App\Annotation\ReverseProxy;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Entity\Calendrier;
use App\Entity\User;
use App\Parser\ProgrammeTVParser;
use App\Social\FacebookAdmin;
use Doctrine\Common\Cache\Cache as DoctrineCache;
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

    /**
     * @Route("/_private/tendances/{id}", name="app_event_tendances", requirements={"id": "\d+"})
     * @ReverseProxy(expires="1 year")
     */
    public function tendances(Event $event, DoctrineCache $memoryCache, FacebookAdmin $facebookAdmin)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $em->getRepository(Event::class);

        $participer = false;
        $interet = false;

        /** @var User $user */
        $user = $this->getUser();
        if ($user) {
            $repoCalendrier = $em->getRepository(Calendrier::class);
            $calendrier = $repoCalendrier->findOneBy(['user' => $user, 'event' => $event]);
            if (null !== $calendrier) {
                $participer = $calendrier->getParticipe();
                $interet = $calendrier->getInteret();
            }

            if ($event->getFacebookEventId() && $user->getInfo() && $user->getInfo()->getFacebookId()) {
                $key = 'users.' . $user->getId() . '.stats.' . $event->getId();
                if (!$memoryCache->contains($key)) {
                    $stats = $facebookAdmin->getUserEventStats($event->getFacebookEventId(), $user->getInfo()->getFacebookId(), $user->getInfo()->getFacebookAccessToken());
                    $memoryCache->save($key, $stats);
                }
                $stats = $memoryCache->fetch($key);

                if ($stats['participer'] || $stats['interet']) {
                    if (null === $calendrier) {
                        $calendrier = new Calendrier();
                        $calendrier->setUser($user)->setEvent($event);
                    }

                    $participer = $calendrier->getParticipe() || $stats['participer'];
                    $interet = $calendrier->getInteret() || $stats['interet'];

                    $calendrier
                        ->setParticipe($participer)
                        ->setInteret($interet);

                    $em->persist($calendrier);
                    $em->flush();
                }
            }
        }

        return $this->render('City/Hinclude/tendances.html.twig', [
            'event' => $event,
            'tendances' => $repo->findAllTendances($event),
            'count_participer' => $event->getParticipations() + $event->getFbParticipations(),
            'count_interets' => $event->getInterets() + $event->getFbInterets(),
            'participer' => $participer,
            'interet' => $interet,
        ]);
    }
}
