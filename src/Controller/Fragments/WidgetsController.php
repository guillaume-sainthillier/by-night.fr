<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Fragments;

use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Repository\CalendrierRepository;
use App\Annotation\ReverseProxy;
use App\Controller\TBNController as BaseController;
use App\Entity\Calendrier;
use App\Entity\Event;
use App\Entity\User;
use App\Picture\EventProfilePicture;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WidgetsController extends BaseController
{
    const WIDGET_ITEM_LIMIT = 7;
    /**
     * @var \App\Repository\UserRepository
     */
    private $userRepository;
    /**
     * @var \App\Repository\CalendrierRepository
     */
    private $calendrierRepository;
    public function __construct(RequestStack $requestStack, EventRepository $eventRepository, UserRepository $userRepository, CalendrierRepository $calendrierRepository)
    {
        parent::__construct($requestStack, $eventRepository);
        $this->userRepository = $userRepository;
        $this->calendrierRepository = $calendrierRepository;
    }

    /**
     * @Route("/top/membres/{page}", name="app_agenda_top_membres", requirements={"page": "\d+"})
     * @ReverseProxy(expires="6 hours")
     *
     * @param int $page
     *
     * @return Response
     */
    public function topMembres($page = 1)
    {
        if ($page <= 1) {
            $page = 1;
        }

        $em = $this->getDoctrine()->getManager();
        $repo = $this->userRepository;

        $count = $repo->findMembresCount();
        $current = $page * self::WIDGET_ITEM_LIMIT;

        $hasNextLink = $current < $count ? $this->generateUrl('app_agenda_top_membres', [
            'page' => $page + 1,
        ]) : null;

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
    public function tendances(Event $event, EventProfilePicture $eventProfilePicture)
    {
        $em = $this->getDoctrine()->getManager();
        $repo = $this->eventRepository;

        $participer = false;
        $interet = false;

        /** @var User $user */
        $user = $this->getUser();
        if ($user) {
            $repoCalendrier = $this->calendrierRepository;
            $calendrier = $repoCalendrier->findOneBy(['user' => $user, 'event' => $event]);
            if (null !== $calendrier) {
                $participer = $calendrier->getParticipe();
                $interet = $calendrier->getInteret();
            }
        }

        $link = $this->generateUrl('app_event_details', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $event->getLocationSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $eventProfile = $eventProfilePicture->getOriginalPicture($event);

        $page = new Page([
            'url' => $link,
            'title' => $event->getNom(),
            'text' => $event->getDescriptif(),
            'image' => $eventProfile,
        ]);

        return $this->render('City/Hinclude/tendances.html.twig', [
            'event' => $event,
            'tendances' => $repo->findAllTendances($event),
            'count' => $event->getParticipations() + $event->getFbParticipations() + $event->getInterets() + $event->getFbInterets(),
            'participer' => $participer,
            'interet' => $interet,
            'shares' => [
                'facebook' => $page->facebook,
                'twitter' => $page->twitter,
            ],
        ]);
    }
}
