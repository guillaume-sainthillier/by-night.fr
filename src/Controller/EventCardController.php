<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Controller;

use App\Entity\Event;
use App\Picture\EventCard;
use App\Security\Voter\EventVoter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The social image of an event (og:image of its page): see EventCard.
 */
final class EventCardController extends AbstractController
{
    #[Route(path: '/og/soiree/{id<%patterns.id%>}-{hash<[0-9a-f]{10}>}.jpg', name: 'app_event_card', methods: ['GET'])]
    public function card(Event $event, string $hash, EventCard $card): Response
    {
        // A draft gives nothing away, its poster included: the page of a hidden draft shares the image of the site
        if (!$this->isGranted(EventVoter::VIEW, $event)) {
            throw $this->createNotFoundException();
        }

        // The card of a former date or poster: the network that kept its URL gets the current one
        if ($hash !== $card->hash($event)) {
            return $this->redirectToRoute('app_event_card', ['id' => $event->getId(), 'hash' => $card->hash($event)], Response::HTTP_MOVED_PERMANENTLY);
        }

        $response = new BinaryFileResponse($card->path($event), headers: ['Content-Type' => 'image/jpeg']);
        // The URL changes with what the card shows: browsers and Cloudflare keep it a year
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setSharedMaxAge(31536000);
        $response->setImmutable();

        return $response;
    }
}
