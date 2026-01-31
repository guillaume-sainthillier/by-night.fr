<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\Entity\Event;
use App\Exception\RedirectException;
use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class EventRedirectManager
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        private EventRepository $eventRepository,
    ) {
    }

    /**
     * Get event entity, throwing RedirectException if URL needs correction.
     *
     * @throws RedirectException     when URL needs to be redirected (SEO)
     * @throws NotFoundHttpException when event is not found
     */
    public function getEvent(
        ?int $eventId,
        string $eventSlug,
        string $locationSlug,
        string $routeName,
        array $routeParams = [],
    ): Event {
        // Old route handle
        if (null === $eventId) {
            $event = $this->eventRepository->findOneBy(['slug' => $eventSlug]);
        } else {
            $event = $this->eventRepository->find($eventId);
        }

        if (null === $event) {
            throw new NotFoundHttpException(null === $eventId ? \sprintf('Event with slug "%s" not found', $eventSlug) : \sprintf('Event with id "%d" not found', $eventId));
        }

        // Redirect duplicates to canonical event (301 for SEO)
        if ($event->isDuplicate()) {
            $canonical = $event->getCanonicalEvent();

            throw new RedirectException($this->router->generate($routeName, array_merge(['id' => $canonical->getId(), 'slug' => $canonical->getSlug(), 'location' => $canonical->getLocationSlug()], $routeParams)));
        }

        // Check for URL mismatch (wrong slug, id, or location)
        if (null === $this->requestStack->getParentRequest() && (
            null === $eventId
            || $event->getSlug() !== $eventSlug
            || $event->getLocationSlug() !== $locationSlug
        )) {
            throw new RedirectException($this->router->generate($routeName, array_merge(['id' => $event->getId(), 'slug' => $event->getSlug(), 'location' => $event->getLocationSlug()], $routeParams)));
        }

        return $event;
    }
}
