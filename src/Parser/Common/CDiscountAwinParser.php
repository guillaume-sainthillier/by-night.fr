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

final class CDiscountAwinParser extends AbstractAwinParser
{
    // Note: merchant_image_url returns 404, use aw_image_url instead (200x200 proxied images)
    private const string DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/fid/48133/format/csv/language/fr/delimiter/%2C/compression/gzip/columns/aw_deep_link,aw_image_url,merchant_product_id,product_name,description,search_price,custom_1,custom_2,custom_3,custom_4,custom_6/';

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
        $hours = null;
        if (preg_match('#le (\d{2}/\d{2}/\d{4}) à (\d{1,2})h#', $dateStr, $matches)) {
            $startDate = DateTimeImmutable::createFromFormat('d/m/Y', $matches[1]);
            $hours = \sprintf('À %sh', $matches[2]);
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
        $event->hours = $hours;
        $event->source = $data['aw_deep_link'];
        $event->name = $data['product_name'];
        $event->description = $data['description'] ?? '';
        $event->imageUrl = $data['aw_image_url'] ?? '';
        $event->prices = \sprintf('%s€', $data['search_price']);

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

        return $event;
    }
}
