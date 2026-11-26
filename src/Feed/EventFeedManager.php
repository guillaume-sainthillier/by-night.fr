<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Feed;

use App\Cdn\EventPageCache;
use App\Entity\Event;
use App\Entity\Place;
use App\Entity\Publisher;
use App\Repository\EventRepository;
use App\Transfer\Export\EventCalendarWriter;
use App\Utils\Utm;
use DateInterval;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The events of a publisher or of a venue outside its page: a calendar (ICS) a calendar app subscribes to, and a
 * widget its website embeds in an iframe. Both are public and the same for everyone (their routes are stateless):
 * Cloudflare keeps them an hour. Nothing purges them when an event is added or changed, the hour is the delay.
 */
final readonly class EventFeedManager
{
    /** The events already over that the calendar keeps: the app would otherwise drop last weekend's */
    private const string CALENDAR_PAST = '-30 days';

    /** The events a calendar lists at most */
    private const int CALENDAR_EVENTS = 200;

    /** How often the app should read the calendar again (X-PUBLISHED-TTL; Google keeps its own pace) */
    private const string CALENDAR_REFRESH = 'PT3H';

    public const int WIDGET_EVENTS = 5;

    public const int WIDGET_MAX_EVENTS = 20;

    private const int SHARED_MAX_AGE = 3600;

    /** A browser or an app that reads it twice in a row: none of it depends on who asks */
    private const int MAX_AGE = 900;

    public function __construct(
        private EventRepository $eventRepository,
        private EventCalendarWriter $calendarWriter,
        private Environment $twig,
        private ClockInterface $clock,
    ) {
    }

    public function createCalendarResponse(Publisher|Place $owner): Response
    {
        $events = $this->findEvents($owner, $this->clock->now()->modify(self::CALENDAR_PAST), self::CALENDAR_EVENTS);
        $calendar = $this->calendarWriter->render($events, \sprintf('%s · By Night', $owner->getName()), ttl: new DateInterval(self::CALENDAR_REFRESH), utm: Utm::link('calendar', 'calendar', self::campaignOf($owner)));

        $filename = \sprintf('%s.ics', $owner->getSlug());
        $response = new Response($calendar, Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $filename),
            'X-Robots-Tag' => 'noindex',
        ]);

        return $this->share($response, $owner);
    }

    /**
     * @param int  $limit the number of events to list, between 1 and WIDGET_MAX_EVENTS
     * @param bool $dark  the dark theme, for a website of dark colours
     */
    public function createWidgetResponse(Publisher|Place $owner, int $limit, bool $dark): Response
    {
        $events = $this->findEvents($owner, $this->clock->now(), max(1, min(self::WIDGET_MAX_EVENTS, $limit)));

        $response = new Response($this->twig->render('widget/events.html.twig', [
            'owner' => $owner,
            'publisher' => $owner instanceof Publisher ? $owner : null,
            'place' => $owner instanceof Place ? $owner : null,
            'events' => $events,
            'dark' => $dark,
            // Its frame is ours: a click from it would count as a visit from By Night itself
            'utm' => Utm::link('widget', 'embed', self::campaignOf($owner)),
        ]));
        // Any website may frame it: it is made for that
        $response->headers->set('Content-Security-Policy', 'frame-ancestors *');
        // The listing of a page of its own: not one search engines should send visitors to
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $this->share($response, $owner);
    }

    /**
     * @return list<Event>
     */
    private function findEvents(Publisher|Place $owner, DateTimeImmutable $since, int $limit): array
    {
        /** @var list<Event> $events */
        $events = $this->eventRepository
            ->createFeedQueryBuilder($owner, $since)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Their sessions, their venue and its city (the URL of their page), and the picture they borrow
        $this->eventRepository->loadAllEager($events, ['view' => 'events:agenda:list']);

        return $events;
    }

    /**
     * Whose widget or calendar a visit comes from: "publisher-le-bikini", "place-le-bikini"
     */
    private static function campaignOf(Publisher|Place $owner): string
    {
        return ($owner instanceof Publisher ? 'publisher-' : 'place-') . $owner->getSlug();
    }

    private function share(Response $response, Publisher|Place $owner): Response
    {
        $response->setPublic();
        $response->setMaxAge(self::MAX_AGE);
        $response->setSharedMaxAge(self::SHARED_MAX_AGE);
        // Its name or its venue's address changes purge it with its events' pages (EventPageCachePurgeListener)
        $response->headers->set('Cache-Tag', $owner instanceof Publisher ? EventPageCache::publisherTag((int) $owner->getId()) : EventPageCache::placeTag((int) $owner->getId()));

        return $response;
    }
}
