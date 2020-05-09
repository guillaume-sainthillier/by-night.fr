<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Fragments;

use App\Annotation\ReverseProxy;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Entity\User;
use App\Picture\EventProfilePicture;
use App\Repository\UserEventRepository;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WidgetsController extends BaseController
{
    const WIDGET_ITEM_LIMIT = 7;

    /**
     * @Route("/top/membres/{page<%patterns.page%>}", name="app_agenda_top_membres", methods={"GET"})
     * @ReverseProxy(expires="6 hours")
     */
    public function topMembres(UserRepository $userRepository, int $page = 1): Response
    {
        $count = $userRepository->findMembresCount();
        $current = $page * self::WIDGET_ITEM_LIMIT;

        $hasNextLink = $current < $count ? $this->generateUrl('app_agenda_top_membres', [
            'page' => $page + 1,
        ]) : null;

        return $this->render('City/Hinclude/membres.html.twig', [
            'membres' => $userRepository->findTopMembres($page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current' => $current,
            'count' => $count,
        ]);
    }

    /**
     * @Route("/_private/tendances/{id<%patterns.id%>}", name="app_event_tendances", methods={"GET"})
     * @ReverseProxy(expires="1 year")
     */
    public function tendances(Event $event, EventProfilePicture $eventProfilePicture, EventRepository $eventRepository, UserEventRepository $userEventRepository): Response
    {
        $participer = false;
        $interet = false;

        /** @var User $user */
        $user = $this->getUser();
        if ($user) {
            $userEvent = $userEventRepository->findOneBy(['user' => $user, 'event' => $event]);
            if (null !== $userEvent) {
                $participer = $userEvent->getParticipe();
                $interet = $userEvent->getInteret();
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
            'tendances' => $eventRepository->findAllTendances($event),
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
