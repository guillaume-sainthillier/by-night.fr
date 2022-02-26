<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Fragments;

use App\Annotation\ReverseProxy;
use App\Controller\AbstractController as BaseController;
use App\Entity\Event;
use App\Picture\EventProfilePicture;
use App\Repository\EventRepository;
use App\Repository\UserEventRepository;
use App\Repository\UserRepository;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WidgetsController extends BaseController
{
    public const WIDGET_ITEM_LIMIT = 7;

    /**
     * @ReverseProxy(expires="6 hours")
     */
    #[Route(path: '/top/membres/{page<%patterns.page%>}', name: 'app_agenda_top_users', methods: ['GET'])]
    public function topUsers(UserRepository $userRepository, int $page = 1): Response
    {
        $count = $userRepository->getCount();
        $current = $page * self::WIDGET_ITEM_LIMIT;
        $hasNextLink = $current < $count ? $this->generateUrl('app_agenda_top_users', [
            'page' => $page + 1,
        ]) : null;

        return $this->render('location/hinclude/top-users.html.twig', [
            'users' => $userRepository->findAllTopUsers($page, self::WIDGET_ITEM_LIMIT),
            'hasNextLink' => $hasNextLink,
            'current' => $current,
            'count' => $count,
        ]);
    }

    /**
     * @ReverseProxy(expires="1 year")
     */
    #[Route(path: '/_private/tendances/{id<%patterns.id%>}', name: 'app_event_trends', methods: ['GET'])]
    public function trends(Event $event, EventProfilePicture $eventProfilePicture, EventRepository $eventRepository, UserEventRepository $userEventRepository): Response
    {
        $participer = false;
        $interet = false;
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

        return $this->render('location/hinclude/trends.html.twig', [
            'event' => $event,
            'tendances' => $eventRepository->findAllTrends($event),
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
