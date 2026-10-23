<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Outing;

use App\Entity\Event;
use App\Enum\EventStatus;

/**
 * What a member knows of an outing of theirs: its dates, its venue and its status, as their last alert told them, or
 * as the event was when they said "J'y vais" (UserEvent::$knownState). OutingChanges compares it to the event as it
 * is now.
 */
final readonly class OutingState
{
    /**
     * @param ?string $start  the first day, Y-m-d
     * @param ?string $end    the last day, Y-m-d
     * @param ?string $time   the start time of the first day, H:i
     * @param ?int    $place  the venue's id
     * @param ?string $venue  the venue's name, as the alert names it
     * @param ?string $city   the venue's city, as the alert names it
     * @param ?string $status the event's EventStatus
     */
    public function __construct(
        public ?string $start = null,
        public ?string $end = null,
        public ?string $time = null,
        public ?int $place = null,
        public ?string $venue = null,
        public ?string $city = null,
        public ?string $status = null,
    ) {
    }

    public static function fromEvent(Event $event): self
    {
        $place = $event->getPlace();

        return new self(
            start: $event->getStartDate()?->format('Y-m-d'),
            end: ($event->getEndDate() ?? $event->getStartDate())?->format('Y-m-d'),
            time: $event->getStartTime()?->format('H:i'),
            place: $place?->getId(),
            venue: $place?->getName() ?? $event->getPlaceName(),
            city: $place?->getCityName() ?? $event->getPlaceCity(),
            status: $event->getStatus()?->value,
        );
    }

    /**
     * @param array<string, mixed> $state as toArray() stored it
     */
    public static function fromArray(array $state): self
    {
        $string = static fn (string $key): ?string => \is_string($state[$key] ?? null) && '' !== $state[$key] ? $state[$key] : null;

        return new self(
            start: $string('start'),
            end: $string('end'),
            time: $string('time'),
            place: is_numeric($state['place'] ?? null) ? (int) $state['place'] : null,
            venue: $string('venue'),
            city: $string('city'),
            status: $string('status'),
        );
    }

    /**
     * @return array{start: ?string, end: ?string, time: ?string, place: ?int, venue: ?string, city: ?string, status: ?string}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
            'time' => $this->time,
            'place' => $this->place,
            'venue' => $this->venue,
            'city' => $this->city,
            'status' => $this->status,
        ];
    }

    public function getStatus(): ?EventStatus
    {
        return null === $this->status ? null : EventStatus::tryFrom($this->status);
    }
}
