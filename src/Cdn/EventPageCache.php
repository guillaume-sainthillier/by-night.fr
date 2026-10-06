<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Cdn;

use App\Entity\Event;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * How long Cloudflare keeps a visitor's event page, and the tags that purge it.
 *
 * Crawlers fetch the same event days apart, so the page stays cached for a day (a week once the event has ended,
 * when it hardly changes any more) and is purged by tag whenever what it shows changes
 * (EventPageCachePurgeListener). An upcoming event's page never outlives the midnight it turns into an ended one.
 */
final readonly class EventPageCache
{
    /** Every event page: `bin/console app:cdn:purge-events` purges them all in one call */
    public const string TAG = 'event';

    private const int UPCOMING_MAX_AGE = 86400;

    private const int ENDED_MAX_AGE = 604800;

    /** Below this, Cloudflare would ask the origin again at once: not worth a cache entry */
    private const int MIN_MAX_AGE = 60;

    public function __construct(private ClockInterface $clock)
    {
    }

    public static function eventTag(int $eventId): string
    {
        return 'event-' . $eventId;
    }

    public static function placeTag(int $placeId): string
    {
        return 'place-' . $placeId;
    }

    public function applyTo(Response $response, Event $event): Response
    {
        $response->setSharedMaxAge($this->sharedMaxAge($event));
        $response->headers->set('Cache-Tag', implode(',', $this->tags($event)));

        return $response;
    }

    public function sharedMaxAge(Event $event): int
    {
        $lastDay = $event->getEndDate() ?? $event->getStartDate();
        if (null === $lastDay) {
            return self::UPCOMING_MAX_AGE;
        }

        // EventIndexingPolicy::hasEnded(): the event has ended once its last day is before today
        $now = $this->clock->now();
        $endedAt = $lastDay->setTimezone($now->getTimezone())->setTime(0, 0)->modify('+1 day');
        $untilEnded = $endedAt->getTimestamp() - $now->getTimestamp();
        if ($untilEnded <= 0) {
            return self::ENDED_MAX_AGE;
        }

        return max(self::MIN_MAX_AGE, min(self::UPCOMING_MAX_AGE, $untilEnded));
    }

    /**
     * @return list<string>
     */
    public function tags(Event $event): array
    {
        $tags = [self::TAG];
        if (null !== $eventId = $event->getId()) {
            $tags[] = self::eventTag($eventId);
        }
        if (null !== $placeId = $event->getPlace()?->getId()) {
            $tags[] = self::placeTag($placeId);
        }

        // The page shows the picture of another member of its family: a new one there must reach it
        $shown = $event->getShownPictureEvent();
        if ($shown !== $event && null !== $lenderId = $shown->getId()) {
            $tags[] = self::eventTag($lenderId);
        }

        return $tags;
    }
}
