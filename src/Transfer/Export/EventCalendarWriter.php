<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Transfer\Export;

use App\Entity\Event;
use App\Entity\EventTimesheet;
use App\Enum\EventStatus;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event as CalendarEvent;
use Eluceo\iCal\Domain\Entity\TimeZone;
use Eluceo\iCal\Domain\Enum\EventStatus as CalendarEventStatus;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\GeographicPosition;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
use Eluceo\iCal\Domain\ValueObject\Occurrence;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Events as a calendar (ICS), for a calendar app: one entry per session, at its times in Paris' time zone, or over its
 * whole days without times. A member's export is imported once (write()); their outings' feed is subscribed to, and
 * read again by the app as often as its X-PUBLISHED-TTL asks (render()).
 */
final readonly class EventCalendarWriter
{
    private const string TIME_ZONE = 'Europe/Paris';

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @param iterable<Event> $events
     */
    public function write(string $path, iterable $events): void
    {
        file_put_contents($path, $this->render($events, 'Mes événements By Night'));
    }

    /**
     * @param iterable<Event> $events
     * @param list<int>       $tentative the events to mark as maybe (TENTATIVE): the others take place (CONFIRMED),
     *                                   unless cancelled
     * @param ?DateInterval   $ttl       how often a subscribed app should read the calendar again
     */
    public function render(iterable $events, string $name, array $tentative = [], ?DateInterval $ttl = null): string
    {
        $entries = [];
        $first = $last = null;
        foreach ($events as $event) {
            foreach ($this->entries($event, \in_array($event->getId(), $tentative, true)) as [$entry, $start, $end]) {
                $entries[] = $entry;
                $first = null === $first || $start < $first ? $start : $first;
                $last = null === $last || $end > $last ? $end : $last;
            }
        }

        $calendar = new Calendar($entries)
            ->setProductIdentifier('-//By Night//Événements//FR')
            ->setCalName($name);
        if (null !== $ttl) {
            $calendar->setPublishedTTL($ttl);
        }

        if (null !== $first && null !== $last) {
            $calendar->addTimeZone(TimeZone::createFromPhpDateTimeZone(new DateTimeZone(self::TIME_ZONE), $first, new DateTimeImmutable($last->format('Y-m-d'))->modify('+1 day')));
        }

        return (string) new CalendarFactory()->createCalendar($calendar);
    }

    /**
     * @return list<array{0: CalendarEvent, 1: DateTimeInterface, 2: DateTimeInterface}> an entry per session, with its first and last day
     */
    private function entries(Event $event, bool $tentative): array
    {
        $entries = [];
        $sessions = $event->getOwnTimesheets()->toArray();
        $sessions = [] === $sessions
            ? [[$event->getStartDate(), $event->getEndDate(), $event->getStartTime(), $event->getEndTime()]]
            : array_map(static fn (EventTimesheet $session): array => [$session->getStartAt(), $session->getEndAt(), $session->getStartTime(), $session->getEndTime()], $sessions);

        $url = $this->urlGenerator->generate('app_event_details', ['slug' => $event->getSlug(), 'id' => $event->getId(), 'location' => $event->getLocationSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        foreach ($sessions as $i => [$start, $end, $startTime, $endTime]) {
            if (null === $start) {
                continue;
            }

            $entry = new CalendarEvent(new UniqueIdentifier(\sprintf('event-%d-%d@by-night.fr', $event->getId(), $i)))
                ->setSummary((string) $event->getName())
                ->setDescription(trim(html_entity_decode(strip_tags((string) $event->getDescription()), \ENT_QUOTES | \ENT_HTML5)))
                ->setUrl(new Uri($url))
                ->setOccurrence($this->occurrence($start, $end ?? $start, $startTime, $endTime))
                ->setStatus(match (true) {
                    EventStatus::Cancelled === $event->getStatus() => CalendarEventStatus::CANCELLED(),
                    $tentative => CalendarEventStatus::TENTATIVE(),
                    default => CalendarEventStatus::CONFIRMED(),
                });

            $place = implode(', ', array_filter([$event->getPlaceName(), $event->getPlaceStreet(), trim($event->getPlacePostalCode() . ' ' . $event->getPlaceCity())]));
            if ('' !== $place) {
                $location = new Location($place, $event->getPlaceName());
                if (null !== $event->getLatitude() && null !== $event->getLongitude()) {
                    $location = $location->withGeographicPosition(new GeographicPosition($event->getLatitude(), $event->getLongitude()));
                }

                $entry->setLocation($location);
            }

            $entries[] = [$entry, $start, $end ?? $start];
        }

        return $entries;
    }

    private function occurrence(DateTimeInterface $start, DateTimeInterface $end, ?DateTimeInterface $startTime, ?DateTimeInterface $endTime): Occurrence
    {
        if (null === $startTime) {
            return $start->format('Y-m-d') === $end->format('Y-m-d') ? new SingleDay(new Date($start)) : new MultiDay(new Date($start), new Date($end));
        }

        $zone = new DateTimeZone(self::TIME_ZONE);
        $begin = new DateTimeImmutable($start->format('Y-m-d') . ' ' . $startTime->format('H:i'), $zone);
        $finish = null === $endTime ? $begin->modify('+2 hours') : new DateTimeImmutable($end->format('Y-m-d') . ' ' . $endTime->format('H:i'), $zone);
        // An end earlier than the start goes past midnight
        if ($finish <= $begin) {
            $finish = $finish->modify('+1 day');
        }

        return new TimeSpan(new DateTime($begin, true), new DateTime($finish, true));
    }
}
