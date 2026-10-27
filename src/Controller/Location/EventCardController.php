<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller\Location;

use App\App\AppContext;
use App\Controller\AbstractController as BaseController;
use App\Manager\EventRedirectManager;
use App\Picture\EventCard;
use App\Security\Voter\EventVoter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The social image of an event, next to its page: see EventCard.
 */
final class EventCardController extends BaseController
{
    /**
     * The og:image of the page links it with `?v=<hash>` (EventCard::hash()): that URL is cached a year, and any other
     * version leads to the current one, so a network or a CDN that kept a former card fetches the new one.
     */
    #[Route(path: '/soiree/{slug<%patterns.slug%>}--{id<%patterns.id%>}/og-image.jpg', name: 'app_event_card', methods: ['GET'])]
    public function card(Request $request, AppContext $appContext, EventRedirectManager $eventRedirectManager, EventCard $card, string $slug, int $id): Response
    {
        // A former slug or city, a duplicate: to the card of the canonical page
        $event = $eventRedirectManager->getEvent($id, $slug, $appContext->getLocation()->getSlug(), 'app_event_card');

        // A draft gives nothing away, its poster included: the page of a hidden draft shares the image of the site
        if (!$this->isGranted(EventVoter::VIEW, $event)) {
            throw $this->createNotFoundException();
        }

        $hash = $card->hash($event);
        if ($request->query->get('v') !== $hash) {
            return $this->redirectToRoute('app_event_card', [
                'location' => $event->getLocationSlug(),
                'slug' => $event->getSlug(),
                'id' => $event->getId(),
                'v' => $hash,
            ]);
        }

        $response = new BinaryFileResponse($card->path($event), headers: ['Content-Type' => 'image/jpeg']);
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setSharedMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }
}
