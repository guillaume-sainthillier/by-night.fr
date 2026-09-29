<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Manager;

use App\App\Location;
use App\Dto\WidgetData\EventsWidgetData;
use App\Dto\WidgetData\TrendsWidgetData;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserEventRepository;
use App\Utils\PaginateTrait;
use App\Utils\ShareLinks;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class WidgetsManager
{
    use PaginateTrait;

    public const int WIDGET_ITEM_LIMIT = 8;

    public function __construct(
        private EventRepository $eventRepository,
        private UserEventRepository $userEventRepository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getNextEventsData(Event $event, Location $location, int $page = 1): ?EventsWidgetData
    {
        if (!$event->getPlace() instanceof Place) {
            return null;
        }

        $paginator = $this->createMultipleEagerLoadingPaginator(
            $this->eventRepository->findAllNextQueryBuilder($event),
            $this->eventRepository,
            $page,
            self::WIDGET_ITEM_LIMIT,
            ['view' => 'events:widget:next-events'],
        );

        $hasNextLink = $paginator->hasNextPage() ? $this->urlGenerator->generate('app_widget_next_events', [
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'location' => $location->getSlug(),
            'page' => $page + 1,
        ]) : null;

        return new EventsWidgetData(
            paginator: $paginator,
            place: $event->getPlace(),
            hasNextLink: $hasNextLink,
        );
    }

    public function getSimilarEventsData(Event $event, Location $location, int $page = 1): EventsWidgetData
    {
        $paginator = $this->createMultipleEagerLoadingPaginator(
            $this->eventRepository->findAllSimilarsQueryBuilder($event),
            $this->eventRepository,
            $page,
            self::WIDGET_ITEM_LIMIT,
            ['view' => 'events:widget:similar-events'],
        );

        $hasNextLink = $paginator->hasNextPage() ? $this->urlGenerator->generate('app_widget_similar_events', [
            'location' => $location->getSlug(),
            'slug' => $event->getSlug(),
            'id' => $event->getId(),
            'page' => $page + 1,
        ]) : null;

        return new EventsWidgetData(
            paginator: $paginator,
            place: $event->getPlace(),
            hasNextLink: $hasNextLink,
        );
    }

    public function getTrendsData(Event $event, ?User $user, string $eventUrl): TrendsWidgetData
    {
        $participate = false;
        $interest = false;

        if (null !== $user) {
            $userEvent = $this->userEventRepository->findOneBy(['user' => $user, 'event' => $event]);
            if (null !== $userEvent) {
                $participate = $userEvent->getGoing();
                $interest = $userEvent->getWish();
            }
        }

        return new TrendsWidgetData(
            event: $event,
            participate: $participate,
            interest: $interest,
            count: $event->getParticipations() + $event->getFbParticipations() + $event->getInterests() + $event->getFbInterests(),
            shares: ShareLinks::forPage($eventUrl, $event->getName() ?? ''),
        );
    }
}
