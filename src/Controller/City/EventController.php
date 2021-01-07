<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\City;

use App\Annotation\ReverseProxy;
use App\App\Location;
use App\Controller\TBNController as BaseController;
use App\Entity\Event;
use App\Event\EventCheckUrlEvent;
use App\Event\Events;
use App\Picture\EventProfilePicture;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use SocialLinks\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class EventController extends BaseController
{
    /**
     * @Route("/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}", name="app_event_details", methods={"GET"})
     * @Route("/soiree/{slug<%patterns.slug%>}", name="app_event_details_old", methods={"GET"})
     * @ReverseProxy(expires="+1 month")
     */
    public function details(Location $location, EventDispatcherInterface $eventDispatcher, string $slug, ?int $id = null): Response
    {
        $eventCheck = new EventCheckUrlEvent($id, $slug, $location->getSlug(), 'app_event_details');
        $eventDispatcher->dispatch($eventCheck, Events::CHECK_EVENT_URL);
        if (null !== $eventCheck->getResponse()) {
            return $eventCheck->getResponse();
        }
        $event = $eventCheck->getEvent();

        return $this->render('City/Event/get.html.twig', [
            'location' => $location,
            'event' => $event,
        ]);
    }

    /**
     * @Cache(expires="+12 hours", smaxage="43200")
     */
    public function share(Event $event, EventProfilePicture $eventProfilePicture): Response
    {
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

        return $this->render('City/Hinclude/shares.html.twig', [
            'shares' => [
                'facebook' => $page->facebook,
                'twitter' => $page->twitter,
            ],
        ]);
    }
}
