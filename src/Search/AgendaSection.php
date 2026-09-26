<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Search;

use App\Entity\Event;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * A day of an agenda page and the events listed under it ("Samedi 27 septembre").
 *
 * An event is listed on the day the page sorts it by (EventElasticaRepository::createSearchQuery()): the soonest-ending
 * of its sessions in the searched window, so an exhibition comes on its last day. It takes place that day, and the days
 * follow each other in order, each one once. Grouping by the first day instead would list an exhibition running since
 * May under a heading of May, among the events of October.
 */
final readonly class AgendaSection
{
    /**
     * @param list<Event> $events
     */
    public function __construct(
        public DateTimeImmutable $day,
        public array $events,
    ) {
    }

    /**
     * @param iterable<Event>        $events the events of the page, in its order
     * @param DateTimeInterface      $from   the first day of the searched window
     * @param DateTimeInterface|null $to     its last day, if any
     *
     * @return list<self>
     */
    public static function fromEvents(iterable $events, DateTimeInterface $from, ?DateTimeInterface $to = null): array
    {
        $fromDay = $from->format('Y-m-d');
        $toDay = $to?->format('Y-m-d');

        /** @var list<array{day: string, events: list<Event>}> $sections */
        $sections = [];
        $last = null;
        foreach ($events as $event) {
            $day = self::dayOf($event, $fromDay, $toDay);
            if ($day !== $last) {
                $sections[] = ['day' => $day, 'events' => []];
                $last = $day;
            }

            $sections[\count($sections) - 1]['events'][] = $event;
        }

        return array_map(static fn (array $section): self => new self(new DateTimeImmutable($section['day']), $section['events']), $sections);
    }

    /**
     * The soonest-ending of the event's sessions in the window, as the sort has it, but within the window: a festival
     * running from Saturday to Tuesday is listed on Sunday when the weekend is searched.
     */
    private static function dayOf(Event $event, string $from, ?string $to): string
    {
        $ends = [];
        foreach ($event->getSessions() as $session) {
            $start = $session->getStartAt()?->format('Y-m-d');
            $end = ($session->getEndAt() ?? $session->getStartAt())?->format('Y-m-d');
            if (null !== $end && $end >= $from && (null === $to || $start <= $to)) {
                $ends[] = $end;
            }
        }

        $day = [] === $ends ? $from : min($ends);

        return null !== $to ? min($day, $to) : $day;
    }
}
