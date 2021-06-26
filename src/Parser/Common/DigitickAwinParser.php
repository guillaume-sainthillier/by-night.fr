<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use DateTime;

class DigitickAwinParser extends AbstractAwinParser
{
    private const DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/language/fr/fid/22739/columns/aw_deep_link,product_name,aw_product_id,merchant_product_id,merchant_image_url,description,merchant_category,search_price,Tickets%3Aevent_date,Tickets%3Avenue_name,Tickets%3Avenue_address,Tickets%3Aevent_name,Tickets%3Alongitude,Tickets%3Alatitude,is_for_sale/format/xml-tree/compression/gzip/';

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
    protected function getInfoEvents(array $datas): array
    {
        if ('0' === $datas['is_for_sale']) {
            return [];
        }

        $fromDate = DateTime::createFromFormat('Y-m-d H:i:s', $datas['event_date']);
        $toDate = DateTime::createFromFormat('Y-m-d H:i:s', $datas['event_date']);
        $horaires = sprintf('À %s', $fromDate->format('H\hi'));

        if (!preg_match('#^(.+) (\d{5}) (.+)$#', $datas['venue_address'], $placeMatches)) {
            return [];
        }

        return [
            'external_id' => 'DGT-' . $datas['merchant_product_id'],
            'date_debut' => $fromDate,
            'date_fin' => $toDate,
            'horaires' => $horaires,
            'source' => $datas['aw_deep_link'],
            'nom' => $datas['event_name'],
            'descriptif' => nl2br(trim($this->replaceBBCodes($datas['description']))),
            'url' => $datas['merchant_image_url'],
            'tarif' => sprintf('%s€', $datas['search_price']),
            'placeName' => $datas['venue_name'],
            'placePostalCode' => $placeMatches[2],
            'placeCity' => $placeMatches[3],
            'placeStreet' => $placeMatches[1],
            'placeCountryName' => 'France',
            'latitude' => (float) $datas['latitude'],
            'longitude' => (float) $datas['longitude'],
            'type_manifestation' => 'Expo' === $datas['merchant_category'] ? 'Exposition' : $datas['merchant_category'],
        ];
    }

    private function replaceBBCodes($text): ?string
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

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'SeeTickets (ex Digitick)';
    }
}
