<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Enum\EventStatus;
use App\Handler\EventHandler;
use App\Parser\AbstractParser;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports the events of the Sowprog API v2 (https://app.sowprog.com/docs/api/v2) through the
 * "sowprog.client" scoped client, which carries the API key and the quotas.
 *
 * Sowprog stores one event per date: two dates of one show are two events, grouped back into
 * one family by the import (EventContentHasher::identity()).
 */
final class SowProgParser extends AbstractParser
{
    /** The host of the relative image paths ("/uploads/original/img_event_xxx.jpg") */
    private const string APP_URL = 'https://app.sowprog.com';

    /** The API maximum */
    private const int PAGE_SIZE = 200;

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        #[Target('sowprog.client')]
        private readonly HttpClientInterface $sowprogClient,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler);
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Sow Prog';
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchEvents(?DateTimeImmutable $since, bool $includePast): iterable
    {
        // The API filters on nothing but the modification date: past events always come along
        $modifiedSince = null === $since ? 0 : 1_000 * self::withSafetyMargin($since)->getTimestamp();

        $page = 1;
        do {
            $data = $this->sowprogClient->request('GET', 'events', ['query' => [
                'page' => $page,
                'pageSize' => self::PAGE_SIZE,
                'modifiedSince' => $modifiedSince,
            ]])->toArray();

            foreach ($data['data'] ?? [] as $eventAsArray) {
                yield $this->mapRecord(fn (): ?EventDto => $this->arrayToDto($eventAsArray), ['id' => $eventAsArray['id'] ?? null]);
            }
        } while ($page++ < ($data['meta']['totalPages'] ?? 0));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToDto(array $data): ?EventDto
    {
        $venue = $data['venue'] ?? null;
        if (!\is_array($venue) || empty($data['dates'])) {
            return null;
        }

        $timesheets = array_map($this->timesheet(...), $data['dates']);

        $prices = [];
        $free = false;
        foreach ($data['dates'] as $date) {
            $free = $free || true === ($date['freeAdmission'] ?? false);
            foreach ($date['prices'] ?? [] as $price) {
                $prices[] = \sprintf(
                    '%s : %s%s',
                    // Some labels end with their own colon ("Tarif concert à 21h :")
                    preg_replace('/[\s:]+$/u', '', (string) $price['label']),
                    self::formatPrice($price['price']),
                    'EUR' === ($price['currency'] ?? 'EUR') ? '€' : $price['currency'],
                );
            }
        }

        // Ticketing first: the first website is the one the event page puts forward
        $links = $data['links'] ?? [];
        usort($links, static fn (array $a, array $b): int => ('TICKETING' === $b['type']) <=> ('TICKETING' === $a['type']));

        $styles = array_values(array_filter(array_map(static fn (array $style): string => trim((string) $style['name']), $data['styles'] ?? [])));

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = (string) $data['id'];
        $event->externalUpdatedAt = new DateTimeImmutable($data['updatedAt']);
        $event->name = self::text($data['title']);
        $event->description = $this->description($data);
        $event->source = 'https://www.sowprog.com/';
        $event->imageUrl = $this->absoluteUrl($data['imageUrl'] ?? null);
        $event->type = $data['category']['name'] ?? null;
        $event->category = [] !== $styles ? TagDto::fromString($styles[0]) : null;
        $event->themes = array_map(TagDto::fromString(...), \array_slice($styles, 1));
        $event->status = match ($data['flag'] ?? null) {
            'CANCELLED' => EventStatus::Cancelled,
            'POSTPONED' => EventStatus::Postponed,
            default => null,
        };
        $event->statusMessage = $data['cancelReason'] ?? null;

        // The dates come in no particular order: the event spans from the earliest to the latest
        $event->timesheets = $timesheets;
        $event->startDate = min(array_map(static fn (EventTimesheetDto $timesheet) => $timesheet->startAt, $timesheets));
        $event->endDate = max(array_map(static fn (EventTimesheetDto $timesheet) => $timesheet->endAt, $timesheets));
        $event->prices = [] !== $prices ? implode(' - ', array_unique($prices)) : ($free ? 'Gratuit' : null);
        $event->websiteContacts = array_values(array_unique(array_column($links, 'url')));
        $event->latitude = isset($venue['latitude']) ? (float) $venue['latitude'] : null;
        $event->longitude = isset($venue['longitude']) ? (float) $venue['longitude'] : null;

        $country = new CountryDto();
        // "FR" as the docs show it; a name would still be resolved (CountryDto::getUniqueKey())
        if (2 === \strlen((string) $venue['country'])) {
            $country->code = $venue['country'];
        } else {
            $country->name = $venue['country'] ?? null;
        }

        $city = new CityDto();
        $city->postalCode = $venue['postalCode'] ?? null;
        $city->name = $venue['city'] ?? null;
        $city->country = $country;

        $place = new PlaceDto();
        $place->externalId = (string) $venue['id'];
        $place->name = $venue['name'];
        $place->street = $venue['address'] ?? null;
        $place->city = $city;
        $place->country = $country;

        $event->place = $place;

        return $event;
    }

    /**
     * The API serves the date and the times as datetimes ("2026-11-26T00:00:00.000Z",
     * "1970-01-01T20:30:00.000Z"), the times being the local time of the venue.
     *
     * @param array{date: string, startTime?: string|null, endTime?: string|null} $date
     */
    private function timesheet(array $date): EventTimesheetDto
    {
        $startTime = self::time($date['startTime'] ?? null);
        $endTime = self::time($date['endTime'] ?? null);

        $timesheet = new EventTimesheetDto();
        $timesheet->startAt = new DateTimeImmutable(substr($date['date'], 0, 10));
        // A night that goes past midnight ("De 23h00 à 05h00") ends the next day, as the v1.2 feed had it
        $timesheet->endAt = null !== $startTime && null !== $endTime && $endTime < $startTime ? $timesheet->startAt->modify('+1 day') : $timesheet->startAt;
        $timesheet->startTime = $startTime;
        $timesheet->endTime = $endTime;

        return $timesheet;
    }

    /**
     * "1970-01-01T20:30:00.000Z" or "20:30:00" → 20:30.
     */
    private static function time(?string $time): ?DateTimeImmutable
    {
        if (null === $time || !preg_match('/(\d{2}):(\d{2}):\d{2}/', $time, $matches)) {
            return null;
        }

        return DateTimeImmutable::createFromFormat('!H:i', $matches[1] . ':' . $matches[2]) ?: null;
    }

    /**
     * The texts come with HTML entities now and then ("Jazz Orgue &amp; Guitare", "&#58;").
     */
    private static function text(?string $text): ?string
    {
        return null === $text ? null : html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * The description as HTML: the tagline as a lead, the text in paragraphs (the API serves plain
     * text, whose line breaks the page would lose), then the line-up. The tagline and the line-up
     * are left out when the text already holds them: 1,145 of the 7,117 events of 2026-10-05 open
     * their text with their tagline, and most concerts present their musicians in it.
     *
     * @param array<string, mixed> $data
     */
    private function description(array $data): ?string
    {
        // The venues type on Windows as often as not: "\r\n"
        $text = str_replace(["\r\n", "\r"], "\n", self::text($data['description'] ?? null) ?? '');
        $parts = [];

        $tagline = trim(self::text($data['tagline'] ?? null) ?? '');
        if ('' !== $tagline && !self::mentions($text, $tagline) && self::normalize($tagline) !== self::normalize((string) $data['title'])) {
            $parts[] = \sprintf('<p><strong>%s</strong></p>', self::html($tagline));
        }

        // A blank line ends a paragraph, a single line break stays one
        foreach (preg_split('/\R\s*\R/u', trim($text)) ?: [] as $paragraph) {
            if ('' !== trim($paragraph)) {
                $parts[] = \sprintf('<p>%s</p>', nl2br(self::html(trim($paragraph)), false));
            }
        }

        $artists = $data['artists'] ?? [];
        usort($artists, static fn (array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));
        // "Hugo Corbin - guitare/compos": the text names the musician, not the role
        $named = array_filter($artists, static fn (array $artist): bool => self::mentions($text, preg_replace('/\s[-–—:]\s.*$/u', '', (string) $artist['name']) ?? ''));
        // Nothing to add when the text names them all, or when the only artist is the event itself
        $redundant = 1 === \count($artists) && self::normalize((string) $artists[0]['name']) === self::normalize((string) $data['title']);
        if ([] !== $artists && \count($named) < \count($artists) && !$redundant) {
            $parts[] = \sprintf('<p><strong>Line-up :</strong> %s</p>', implode(', ', array_map(
                static fn (array $artist): string => self::html(trim((string) $artist['name'])) . (empty($artist['instrument']) ? '' : \sprintf(' (%s)', self::html(trim((string) $artist['instrument'])))),
                $artists,
            )));
        }

        return [] !== $parts ? implode("\n", $parts) : null;
    }

    private static function html(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * Whether the text holds the phrase, whatever its case, punctuation and spacing.
     */
    private static function mentions(string $text, string $phrase): bool
    {
        $phrase = self::normalize($phrase);

        return '' !== $phrase && str_contains(self::normalize($text), $phrase);
    }

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower(self::text($text) ?? '')));
    }

    private function absoluteUrl(?string $url): ?string
    {
        if (null === $url || '' === $url) {
            return null;
        }

        return str_starts_with($url, '/') ? self::APP_URL . $url : $url;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'sowprog';
    }

    /**
     * {@inheritDoc}
     *
     * 4.0: API v2, whose event and venue ids are not the ones of the v1.2 feed.
     */
    #[Override]
    public static function getParserVersion(): string
    {
        return '4.0';
    }
}
