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
use App\Picture\EventProfilePicture;
use App\Transfer\Sheet\EventColumn;
use App\Transfer\Sheet\EventStatusLabels;
use DateTimeInterface;

/**
 * Writes an event as the spreadsheet's rows, the way the import reads them back: one row per session, the first one
 * holding the event's columns, the others only its ID and reference and their own dates.
 */
final readonly class EventRowFormatter
{
    public function __construct(private EventProfilePicture $eventProfilePicture)
    {
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return array_map(static fn (EventColumn $column): string => $column->getLabel(), EventColumn::cases());
    }

    /**
     * @return list<list<string>>
     */
    public function rows(Event $event): array
    {
        $sessions = $event->getOwnTimesheets()->toArray();
        usort($sessions, static fn (EventTimesheet $a, EventTimesheet $b): int => $a->getStartAt() <=> $b->getStartAt());

        // A single session: the event's own dates and times
        $rows = \count($sessions) <= 1
            ? [$this->eventValues($event) + [
                EventColumn::StartDate->value => self::date($event->getStartDate()),
                EventColumn::EndDate->value => self::date($event->getEndDate()),
                EventColumn::StartTime->value => self::time($event->getStartTime()),
                EventColumn::EndTime->value => self::time($event->getEndTime()),
                EventColumn::Hours->value => (string) $event->getHours(),
            ]]
            : array_map(fn (EventTimesheet $session, int $i): array => (0 === $i ? $this->eventValues($event) : self::keyValues($event)) + [
                EventColumn::StartDate->value => self::date($session->getStartAt()),
                EventColumn::EndDate->value => self::date($session->getEndAt()),
                EventColumn::StartTime->value => self::time($session->getStartTime()),
                EventColumn::EndTime->value => self::time($session->getEndTime()),
                EventColumn::Hours->value => (string) $session->getHours(),
            ], $sessions, array_keys($sessions));

        return array_map(static fn (array $values): array => array_map(static fn (EventColumn $column): string => $values[$column->value] ?? '', EventColumn::cases()), $rows);
    }

    /**
     * @return array<string, string>
     */
    private static function keyValues(Event $event): array
    {
        return [
            EventColumn::Id->value => (string) $event->getId(),
            EventColumn::Reference->value => (string) $event->getExternalReference(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function eventValues(Event $event): array
    {
        return self::keyValues($event) + [
            EventColumn::Name->value => (string) $event->getName(),
            EventColumn::Description->value => (string) $event->getDescription(),
            EventColumn::Place->value => (string) $event->getPlaceName(),
            EventColumn::Street->value => (string) $event->getPlaceStreet(),
            EventColumn::PostalCode->value => (string) $event->getPlacePostalCode(),
            EventColumn::City->value => (string) $event->getPlaceCity(),
            EventColumn::Country->value => (string) ($event->getPlaceCountry() ?? $event->getPlace()?->getCountry())?->getId(),
            EventColumn::Category->value => (string) $event->getCategory()?->getName(),
            EventColumn::Themes->value => implode(', ', array_map(static fn ($theme): string => (string) $theme->getName(), $event->getThemes()->toArray())),
            EventColumn::Prices->value => (string) $event->getPrices(),
            EventColumn::TicketUrl->value => (string) $event->getTicketUrl(),
            EventColumn::ImageUrl->value => $this->imageUrl($event),
            EventColumn::Websites->value => implode(', ', $event->getWebsiteContacts() ?? []),
            EventColumn::Phones->value => implode(', ', $event->getPhoneContacts() ?? []),
            EventColumn::Emails->value => implode(', ', $event->getMailContacts() ?? []),
            EventColumn::Status->value => EventStatusLabels::toCell($event->getStatus()),
            EventColumn::StatusMessage->value => (string) $event->getStatusMessage(),
            EventColumn::Draft->value => $event->isDraft() ? 'oui' : 'non',
        ];
    }

    /**
     * The picture's address: the one it was downloaded from, else the member's upload on our data host (which an
     * import recognises and keeps as it is).
     */
    private function imageUrl(Event $event): string
    {
        if (null !== $event->getUrl() && '' !== $event->getUrl()) {
            return $event->getUrl();
        }

        return null === $event->getImage()->getName() ? '' : $this->eventProfilePicture->getOriginalPicture($event, own: true);
    }

    private static function date(?DateTimeInterface $date): string
    {
        return (string) $date?->format('d/m/Y');
    }

    private static function time(?DateTimeInterface $time): string
    {
        return (string) $time?->format('H:i');
    }
}
