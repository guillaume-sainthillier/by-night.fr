<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
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

class DigitickAwinParser extends AbstractAwinParser
{
    /**
     * @var string
     */
    private const DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/language/fr/fid/22739/columns/aw_deep_link,product_name,aw_product_id,merchant_product_id,merchant_image_url,description,merchant_category,search_price,Tickets%3Aevent_date,Tickets%3Avenue_name,Tickets%3Avenue_address,Tickets%3Aevent_name,Tickets%3Alongitude,Tickets%3Alatitude,is_for_sale/format/xml-tree/compression/gzip/';

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'SeeTickets (ex Digitick)';
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'awin.digitick';
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
        if ('0' === $data['is_for_sale']) {
            return null;
        }

        if (!preg_match('#^(.+) (\d{5}) (.+)$#', $data['venue_address'], $placeMatches)) {
            return null;
        }

        $startDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['event_date']);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $data['event_date']);
        $hours = sprintf('À %s', $startDate->format('H\hi'));

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $data['merchant_product_id'];
        $event->startDate = $startDate;
        $event->endDate = $endDate;
        $event->hours = $hours;
        $event->source = $data['aw_deep_link'];
        $event->name = $data['event_name'];
        $event->type = 'Expo' === $data['merchant_category'] ? 'Exposition' : $data['merchant_category'];
        $event->description = null === $data['description']
            ? null
            : nl2br(trim($this->replaceBBCodes($data['description'])));
        $event->imageUrl = $data['merchant_image_url'];
        $event->prices = sprintf('%s€', $data['search_price']);
        $event->latitude = (float) $data['latitude'];
        $event->longitude = (float) $data['longitude'];

        $place = new PlaceDto();
        $place->name = $data['venue_name'];
        $place->street = $placeMatches[1];
        $place->externalId = sha1(sprintf(
            '%s %s %s %s',
            $data['venue_name'],
            $placeMatches[1],
            $placeMatches[2],
            $placeMatches[3],
        ));

        $city = new CityDto();
        $city->postalCode = $placeMatches[2];
        $city->name = $placeMatches[3];

        $country = new CountryDto();
        $country->code = 'FR';

        $city->country = $country;

        $place->country = $country;

        $place->city = $city;

        $event->place = $place;

        return $event;
    }

    private function replaceBBCodes(string $text): string
    {
        // BBcode array
        $find = [
            '~\[b\](.*?)\[/b\]~s',
            '~\[i\](.*?)\[/i\]~s',
            '~\[u\](.*?)\[/u\]~s',
            '~\[quote\](.*?)\[/quote\]~s',
            '~\[size=(.*?)\](.*?)\[/size\]~s',
            '~\[color=(.*?)\](.*?)\[/color\]~s',
            '~\[url=(.*?)\](.*?)\[/url\]~s',
            '~\[img\](https?://.*?\.(?:jpg|jpeg|gif|png|bmp))\[/img\]~s',
        ];
        // HTML tags to replace BBcode
        $replace = [
            '<b>$1</b>',
            '<i>$1</i>',
            '<span style="text-decoration:underline;">$1</span>',
            '<pre>$1</pre>',
            '$2',
            '$2',
            '<a href="$1">$2</a>',
            '<img src="$1" alt="" />',
        ];
        // Replacing the BBcodes with corresponding HTML tags
        return preg_replace($find, $replace, $text);
    }
}
