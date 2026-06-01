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
use App\Handler\EventHandler;
use DateTimeImmutable;
use DateTimeInterface;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FnacSpectaclesAwinParser extends AbstractAwinParser
{
    private const string DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/language/fr/fid/23455/columns/aw_deep_link,product_name,merchant_product_id,merchant_image_url,description,search_price,is_for_sale,valid_to,product_short_description,custom_3,custom_4,custom_5,custom_7,Tickets%3Avenue_name,Tickets%3Avenue_address,Tickets%3Aevent_date,Tickets%3Alatitude,Tickets%3Alongitude/format/csv/compression/gzip/';

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%/var/storage/temp')]
        string $tempPath,
        #[Autowire(env: 'AWIN_API_KEY')]
        string $awinApiKey,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler, $httpClient, $tempPath, $awinApiKey);
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Fnac Spectacles';
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'awin.fnac';
    }

    /**
     * {@inheritDoc}
     */
    protected function getAwinUrl(): string
    {
        return self::DATAFEED_URL;
    }

    /**
     * Fnac lists one CSV row per ticket product, so a single show shows up as many
     * near-identical rows (same name, venue and date, only the merchant_product_id
     * and affiliate link differ). We collapse those rows on a stable show identity
     * and publish a single event carrying one timesheet per distinct date.
     *
     * {@inheritDoc}
     */
    #[Override]
    public function parse(bool $incremental): void
    {
        foreach ($this->groupEvents($this->parseCsvFile($this->downloadFeed())) as $event) {
            $this->publish($event);
        }
    }

    /**
     * Collapse the raw CSV rows into one event per show, each carrying a timesheet
     * for every distinct date and a price range spanning all ticket products.
     *
     * @param iterable<array<string, string>> $rows
     *
     * @return list<EventDto>
     */
    protected function groupEvents(iterable $rows): array
    {
        /** @var array<string, EventDto> $events */
        $events = [];
        /** @var array<string, array{min: float, max: float}> $priceRanges */
        $priceRanges = [];

        foreach ($rows as $data) {
            $event = $this->arrayToDto($data);
            if (null === $event) {
                continue;
            }

            // arrayToDto() sets externalId to the show identity shared by every
            // duplicate row, so it doubles as the grouping key.
            $key = (string) $event->externalId;
            $price = (float) ($data['search_price'] ?? 0);

            if (!isset($events[$key])) {
                $events[$key] = $event;
                $priceRanges[$key] = ['min' => $price, 'max' => $price];

                continue;
            }

            $this->mergeDuplicateRow($events[$key], $event);
            $priceRanges[$key]['min'] = min($priceRanges[$key]['min'], $price);
            $priceRanges[$key]['max'] = max($priceRanges[$key]['max'], $price);
        }

        foreach ($events as $key => $event) {
            $this->finalizeEvent($event, $priceRanges[$key]);
        }

        return array_values($events);
    }

    /**
     * {@inheritDoc}
     */
    protected function arrayToDto(array $data): ?EventDto
    {
        $venueName = trim($data['Tickets:venue_name'] ?? '');
        if ('0' === $data['is_for_sale'] || '' === $venueName) {
            return null;
        }

        // Parse start date from Tickets:event_date (YYYY-mm-dd format)
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d', $data['Tickets:event_date']);
        if (false === $startDate) {
            return null;
        }

        // Parse hours from custom_7 (HH:mm format)
        $hours = null;
        $eventTime = trim($data['custom_7'] ?? '');
        if ('' !== $eventTime) {
            $hours = \sprintf('À %s', str_replace(':', 'h', $eventTime));
        }

        // Normalize to a date-only value (timesheets are stored as dates).
        // Also prevents Reject::BAD_EVENT_DATE_INTERVAL.
        $startDate = $startDate->setTime(0, 0);

        // This row is a single performance: model it as one timesheet. Duplicate
        // rows for the same show are folded together in groupEvents().
        $timesheet = new EventTimesheetDto();
        $timesheet->startAt = $startDate;
        $timesheet->endAt = $startDate;
        $timesheet->hours = $hours;

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->startDate = $startDate;
        $event->endDate = $startDate;
        $event->hours = $hours;
        $event->timesheets = [$timesheet];
        $event->source = $data['aw_deep_link'];
        $event->name = $data['product_name'];
        $event->description = nl2br(trim(\sprintf("%s\n\n%s", $data['description'] ?? '', $data['product_short_description'] ?? '')));
        $event->imageUrl = $this->getImageUrl($data['merchant_image_url'] ?? '');
        $event->prices = \sprintf('%s€', $data['search_price']);
        $event->latitude = (float) ($data['Tickets:latitude'] ?? 0);
        $event->longitude = (float) ($data['Tickets:longitude'] ?? 0);

        // CSV mapping:
        // - Tickets:venue_name = venue name
        // - Tickets:venue_address = city name
        // - custom_3 = postal code
        // - custom_4 = street address
        // - custom_5 = country code (FR)
        $place = new PlaceDto();
        $place->name = $venueName;

        $street = trim($data['custom_4'] ?? '');
        $place->street = \in_array($street, ['.', '-', ''], true) ? null : $street;
        $place->externalId = sha1(\sprintf(
            '%s %s %s %s %s',
            $venueName,
            $street,
            $data['Tickets:venue_address'] ?? '',
            $data['custom_3'] ?? '',
            $data['custom_5'] ?? '',
        ));

        $city = new CityDto();
        $city->name = $data['Tickets:venue_address'] ?? '';
        $city->postalCode = $data['custom_3'] ?? '';

        $country = new CountryDto();
        $country->code = $data['custom_5'] ?? '';

        $city->country = $country;

        $place->country = $country;

        $place->city = $city;

        $event->place = $place;

        // Stable show identity: every ticket product for the same show at the same
        // place hashes to the same id, so duplicate rows share a grouping key and
        // re-imports stay idempotent regardless of which products are on sale.
        $event->externalId = sha1(\sprintf('%s|%s', trim((string) $data['product_name']), $place->externalId));

        return $event;
    }

    /**
     * Fold a duplicate row (carrying a single timesheet) into the event already
     * built for this show: add its date if new and widen the event's date range.
     */
    private function mergeDuplicateRow(EventDto $event, EventDto $row): void
    {
        foreach ($row->timesheets as $timesheet) {
            if (!$this->hasTimesheetForDate($event, $timesheet->startAt)) {
                $event->timesheets[] = $timesheet;
            }
        }

        if (null !== $row->startDate && (null === $event->startDate || $row->startDate < $event->startDate)) {
            $event->startDate = $row->startDate;
        }

        if (null !== $row->endDate && (null === $event->endDate || $row->endDate > $event->endDate)) {
            $event->endDate = $row->endDate;
        }
    }

    private function hasTimesheetForDate(EventDto $event, ?DateTimeInterface $date): bool
    {
        if (null === $date) {
            return false;
        }

        $needle = $date->format('Y-m-d');
        foreach ($event->timesheets as $timesheet) {
            if ($timesheet->startAt?->format('Y-m-d') === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Once every duplicate row has been folded in, derive the aggregate fields:
     * chronological timesheets, a shared hours label and the full price range.
     *
     * @param array{min: float, max: float} $priceRange
     */
    private function finalizeEvent(EventDto $event, array $priceRange): void
    {
        usort(
            $event->timesheets,
            static fn (EventTimesheetDto $a, EventTimesheetDto $b): int => ($a->startAt?->getTimestamp() ?? 0) <=> ($b->startAt?->getTimestamp() ?? 0),
        );

        // Surface a single event-level hours label only when every date shares it.
        $distinctHours = array_values(array_unique(array_filter(array_map(
            static fn (EventTimesheetDto $timesheet): ?string => $timesheet->hours,
            $event->timesheets,
        ))));
        $event->hours = 1 === \count($distinctHours) ? $distinctHours[0] : null;

        // Reflect the full ticket price range gathered across the duplicate rows.
        $event->prices = $priceRange['min'] === $priceRange['max']
            ? \sprintf('%s€', $this->formatPrice($priceRange['min']))
            : \sprintf('De %s€ à %s€', $this->formatPrice($priceRange['min']), $this->formatPrice($priceRange['max']));
    }

    private function formatPrice(float $price): string
    {
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    private function getImageUrl(string $url): string
    {
        return $this->cache->get('fnac.urls.' . md5($url), function () use ($url) {
            $imageUrl = str_replace('grand/', '600/', $url);
            try {
                $response = $this->httpClient->request('HEAD', $imageUrl);

                if (200 === $response->getStatusCode()) {
                    return $imageUrl;
                }
            } catch (TransportExceptionInterface) {
            }

            return $url;
        });
    }
}
