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
use App\Security\Voter\EventVoter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class EventRedirectManager
{
    public function __construct(
        private RequestStack $requestStack,
        private UrlGeneratorInterface $router,
        private EventRepository $eventRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * Get event entity, throwing RedirectException if URL needs correction.
     *
     * A draft the visitor cannot see (EventVoter::VIEW) is never redirected: the URL of the redirect holds its slug,
     * so its name, and ids are sequential, so trying them one by one would list every draft. Only its exact URL
     * answers, as someone who was given it asks for it; any other one is not found, as for an event that does not
     * exist.
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
            $event = $this->eventRepository->findOneWithPlace($eventId);
        }

        if (null === $event) {
            throw $this->createNotFoundException($eventId, $eventSlug);
        }

        $isHiddenDraft = !$this->authorizationChecker->isGranted(EventVoter::VIEW, $event);

        // Redirect duplicates to canonical event (301 for SEO)
        if (!$isHiddenDraft && $event->isDuplicate()) {
            $canonical = $event->getCanonicalEvent();

            throw new RedirectException($this->router->generate($routeName, array_merge(['id' => $canonical->getId(), 'slug' => $canonical->getSlug(), 'location' => $canonical->getLocationSlug()], $routeParams)));
        }

        // Check for URL mismatch (wrong slug, id, or location)
        if (null === $this->requestStack->getParentRequest() && (
            null === $eventId
            || $event->getSlug() !== $eventSlug
            || $event->getLocationSlug() !== $locationSlug
        )) {
            if ($isHiddenDraft) {
                throw $this->createNotFoundException($eventId, $eventSlug);
            }

            throw new RedirectException($this->router->generate($routeName, array_merge(['id' => $event->getId(), 'slug' => $event->getSlug(), 'location' => $event->getLocationSlug()], $routeParams)));
        }

        return $event;
    }

    /**
     * As getEvent(), for what shows an event's surroundings (the events at its venue or on its date): a draft the
     * visitor cannot see is not found at all, as they would tell where and when it takes place.
     *
     * @throws RedirectException     when URL needs to be redirected (SEO)
     * @throws NotFoundHttpException when event is not found, or is a draft the visitor cannot see
     */
    public function getVisibleEvent(
        ?int $eventId,
        string $eventSlug,
        string $locationSlug,
        string $routeName,
        array $routeParams = [],
    ): Event {
        $event = $this->getEvent($eventId, $eventSlug, $locationSlug, $routeName, $routeParams);
        if (!$this->authorizationChecker->isGranted(EventVoter::VIEW, $event)) {
            throw $this->createNotFoundException($eventId, $eventSlug);
        }

        return $event;
    }

    private function createNotFoundException(?int $eventId, string $eventSlug): NotFoundHttpException
    {
        return new NotFoundHttpException(null === $eventId ? \sprintf('Event with slug "%s" not found', $eventSlug) : \sprintf('Event with id "%d" not found', $eventId));
    }
}
