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
use App\Enum\EventStatus;
use App\Handler\EventHandler;
use App\Parser\Ticketmaster\TicketmasterCatalogue;
use App\Parser\Ticketmaster\TicketmasterPerformance;
use App\Parser\Ticketmaster\TicketmasterShow;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * CDiscount resells Ticketmaster France: one row per show, keyed by the Ticketmaster show id
 * (merchant_product_id is the "idmanif" of ticketmaster.fr URLs), with a single date and a
 * 200px picture. Ticketmaster's own catalogue completes the shows it knows (93% of them on
 * 2026-10-06) with all their dates, a 2048px picture and the venue's coordinates.
 */
final class CDiscountAwinParser extends AbstractAwinParser
{
    // Note: merchant_image_url returns 404, use aw_image_url instead (200x200 proxied images)
    /**
     * The performances that take place: "offsale" is a show whose sales are closed (sold out,
     * or over before the day), "rescheduled" one already at its new date. "cancelled" and any
     * status the feed would add are left out.
     */
    private const array HELD_STATUSES = ['onsale', 'offsale', 'rescheduled'];

    private const string DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/fid/48133/format/csv/language/fr/delimiter/%2C/compression/gzip/columns/aw_deep_link,aw_image_url,merchant_product_id,product_name,description,search_price,custom_1,custom_2,custom_3,custom_4,custom_6/';

    /** @var array<array-key, TicketmasterShow> the catalogue of the current run, by show id */
    private array $ticketmasterShows = [];

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%/var/storage/temp')]
        string $tempPath,
        #[Autowire(env: 'AWIN_API_KEY')]
        string $awinApiKey,
        private readonly TicketmasterCatalogue $ticketmaster,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler, $httpClient, $tempPath, $awinApiKey);
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'CDiscount';
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'awin.cdiscount';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    protected function fetchEvents(?DateTimeImmutable $since, bool $includePast): iterable
    {
        $this->ticketmasterShows = $this->ticketmaster->shows();

        try {
            yield from parent::fetchEvents($since, $includePast);
        } finally {
            $this->ticketmasterShows = [];
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function getAwinUrl(): string
    {
        return self::DATAFEED_URL;
    }

    /**
     * {@inheritDoc}
     */
    protected function arrayToDto(array $data): ?EventDto
    {
        $venueName = trim($data['custom_6'] ?? '');
        if ('' === $venueName) {
            return null;
        }

        // Parse date from custom_1 (format: "le dd/mm/YYYY à HHh")
        $dateStr = trim($data['custom_1'] ?? '');
        if ('' === $dateStr) {
            return null;
        }

        // Extract date and time from "le 14/02/2020 à 20h" format
        $startTime = null;
        if (preg_match('#le (\d{2}/\d{2}/\d{4}) à (\d{1,2})h#', $dateStr, $matches)) {
            $startDate = DateTimeImmutable::createFromFormat('d/m/Y', $matches[1]);
            $startTime = DateTimeImmutable::createFromFormat('!G', $matches[2]) ?: null;
        } else {
            return null;
        }

        if (false === $startDate) {
            return null;
        }

        // No end date available, use start date
        $endDate = $startDate;

        // Prevents Reject::BAD_EVENT_DATE_INTERVAL
        $endDate = $endDate->setTime(0, 0);

        $startDate = $startDate->setTime(0, 0);

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $data['merchant_product_id'];
        $event->startDate = $startDate;
        $event->endDate = $endDate;
        $event->startTime = $startTime;
        $event->source = $data['aw_deep_link'];
        $event->name = $data['product_name'];
        $event->description = $data['description'] ?? '';
        $event->imageUrl = $data['aw_image_url'] ?? '';
        $event->prices = self::formatPriceRange([$data['search_price']]);

        // CSV mapping:
        // - custom_6 = venue name
        // - custom_4 = street address
        // - custom_2 = city name (e.g., "Paris")
        // - custom_3 = postal code
        $place = new PlaceDto();
        $place->name = $venueName;

        $street = trim($data['custom_4'] ?? '');
        $place->street = \in_array($street, ['.', '-', ''], true) ? null : $street;

        $cityName = trim($data['custom_2'] ?? '');
        $postalCode = trim($data['custom_3'] ?? '');

        $place->externalId = sha1(\sprintf(
            '%s %s %s %s',
            $venueName,
            $street,
            $cityName,
            $postalCode,
        ));

        $city = new CityDto();
        $city->name = $cityName;
        $city->postalCode = $postalCode;

        $country = new CountryDto();
        $country->code = 'FR';

        $city->country = $country;

        $place->country = $country;

        $place->city = $city;

        $event->place = $place;

        $show = $this->ticketmasterShows[$event->externalId] ?? null;
        if (null !== $show) {
            $this->completeWithTicketmaster($event, $show);
        }

        return $event;
    }

    private function completeWithTicketmaster(EventDto $event, TicketmasterShow $show): void
    {
        $event->imageUrl = $show->imageUrl ?? $event->imageUrl;
        if (null !== $event->place && null !== $show->latitude && null !== $show->longitude) {
            $event->place->latitude = $show->latitude;
            $event->place->longitude = $show->longitude;
        }

        $performances = self::heldPerformances($show);
        if ([] === $performances) {
            // Cancelled on every date: CDiscount may still list it
            if ([] !== $show->performances) {
                $event->status = EventStatus::Cancelled;
            }

            return;
        }

        $event->timesheets = array_map(static function (TicketmasterPerformance $performance): EventTimesheetDto {
            $timesheet = new EventTimesheetDto();
            // Prevents Reject::BAD_EVENT_DATE_INTERVAL: the dates are days, the time apart
            $timesheet->startAt = $performance->date;
            $timesheet->endAt = $performance->date;
            $timesheet->startTime = $performance->time;

            return $timesheet;
        }, $performances);
        $event->startDate = $performances[0]->date;
        $event->endDate = $performances[\count($performances) - 1]->date;
        // The cleaner spans the event's times over its sessions
        $event->startTime = null;
    }

    /**
     * The dates of the show that become sessions of the event.
     *
     * @return list<TicketmasterPerformance> in chronological order
     */
    private static function heldPerformances(TicketmasterShow $show): array
    {
        return array_values(array_filter(
            $show->performances,
            static fn (TicketmasterPerformance $performance): bool => \in_array($performance->status, self::HELD_STATUSES, true),
        ));
    }
}
