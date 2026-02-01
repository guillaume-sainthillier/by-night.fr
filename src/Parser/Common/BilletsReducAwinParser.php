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
use App\Dto\PlaceDto;
use DateTimeImmutable;

final class BilletsReducAwinParser extends AbstractAwinParser
{
    private const string DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/fid/47175/format/csv/language/fr/compression/gzip/columns/aw_deep_link,merchant_product_id,product_name,description,merchant_image_url,valid_to,search_price,is_for_sale,custom_1,custom_3,product_short_description,Tickets%3Avenue_name,Tickets%3Alongitude,Tickets%3Alatitude,Tickets%3Aevent_location_address,Tickets%3Aevent_location_city/';

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Billets Reduc';
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'awin.billetsreduc';
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
        $venueName = trim($data['Tickets:venue_name'] ?? '');
        if ('0' === $data['is_for_sale'] || '' === $venueName) {
            return null;
        }

        // Parse sessions from custom_3 (JSON array with SessionDate)
        $sessions = json_decode($data['custom_3'] ?? '[]', true);
        if (!\is_array($sessions) || [] === $sessions) {
            return null;
        }

        // Find the earliest non-sold-out session date as start date
        $startDate = null;
        $seenHours = [];
        foreach ($sessions as $session) {
            if (!isset($session['SessionDate'])) {
                continue;
            }

            // Skip sold out sessions
            if (($session['SoldOut'] ?? 0) === 1) {
                continue;
            }

            $sessionDate = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $session['SessionDate']);
            if (false === $sessionDate) {
                continue;
            }

            if (null === $startDate || $sessionDate < $startDate) {
                $startDate = $sessionDate;
            }

            $seenHours[] = \sprintf('À %s', $sessionDate->format('H\hi'));
        }

        if (null === $startDate) {
            return null;
        }

        // Parse end date from valid_to (M/d/YYYY h:mm:ss AM/PM format)
        $endDate = DateTimeImmutable::createFromFormat('n/j/Y g:i:s A', $data['valid_to']);
        if (false === $endDate) {
            $endDate = $startDate;
        }

        // Determine hours display
        $hours = null;
        $seenHours = array_unique($seenHours);
        if (1 === \count($seenHours)) {
            $hours = $seenHours[0];
        }

        // Prevents Reject::BAD_EVENT_DATE_INTERVAL
        $endDate = $endDate->setTime(0, 0);
        $startDate = $startDate->setTime(0, 0);

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $data['merchant_product_id'];
        $event->startDate = $startDate;
        $event->endDate = $endDate;
        $event->hours = $hours;
        $event->source = $data['aw_deep_link'];
        $event->name = $data['product_name'];
        $event->description = nl2br(trim(\sprintf("%s\n\n%s", $data['description'] ?? '', $data['product_short_description'] ?? '')));
        $event->imageUrl = $this->getHighResImageUrl($data['merchant_image_url'] ?? '');
        $event->prices = \sprintf('%s€', $data['search_price']);
        $event->latitude = (float) ($data['Tickets:latitude'] ?? 0);
        $event->longitude = (float) ($data['Tickets:longitude'] ?? 0);

        // CSV mapping:
        // - Tickets:venue_name = venue name
        // - Tickets:event_location_address = street address
        // - Tickets:event_location_city = city name (e.g., "PARIS 2EME")
        // - custom_1 = postal code
        // - custom_2 = region/city name (e.g., "Paris")
        // - Tickets:event_location_region = region (e.g., "Ile-de-France")
        $place = new PlaceDto();
        $place->name = $venueName;
        $street = trim($data['Tickets:event_location_address'] ?? '');
        $place->street = \in_array($street, ['.', '-', ''], true) ? null : $street;

        $cityName = trim($data['Tickets:event_location_city'] ?? '');
        $postalCode = trim($data['custom_1'] ?? '');

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

        return $event;
    }

    /**
     * Upgrade image URL to high resolution version.
     * BilletsReduc URLs contain /n200/ which can be replaced with /n800/ for larger images.
     */
    private function getHighResImageUrl(string $url): string
    {
        if ('' === $url) {
            return '';
        }

        return str_replace('/n200/', '/n800/', $url);
    }
}
