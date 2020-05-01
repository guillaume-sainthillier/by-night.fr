<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use DateTime;
use App\Producer\EventProducer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class FnacSpectaclesAwinParser extends AbstractAwinParser
{
    private const DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/language/fr/fid/23455/columns/aw_deep_link,product_name,aw_product_id,merchant_product_id,merchant_image_url,description,merchant_category,search_price,is_for_sale,custom_1,valid_to,product_short_description,custom_2,custom_4,custom_6,custom_3,Tickets%3Avenue_address,Tickets%3Alatitude,Tickets%3Alongitude/format/xml-tree/compression/gzip/';

    /** @var CacheInterface */
    private $cache;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, string $tempPath, string $awinApiKey, CacheInterface $memoryCache)
    {
        parent::__construct($logger, $eventProducer, $tempPath, $awinApiKey);
        $this->cache = $memoryCache;
    }

    protected function getAwinUrl(): string
    {
        return self::DATAFEED_URL;
    }

    protected function getInfoEvents(array $datas): array
    {
        if ('0' === $datas['is_for_sale'] || '' === trim($datas['custom_2'])) {
            return [];
        }

        $seenHoraires = [];
        $horaires = null;
        $fromDate = null;
        $startDates = array_filter(explode(';', $datas['custom_1']));

        if (0 === \count($startDates)) {
            return [];
        }
        foreach ($startDates as $startDate) {
            $startDate = DateTime::createFromFormat('d/m/Y H:i', $startDate);
            $seenHoraires[] = sprintf('À %s', $startDate->format('H\hi'));
            $fromDate = $startDate;
        }
        $seenHoraires = array_unique($seenHoraires);

        if (1 === \count($seenHoraires)) {
            $horaires = $seenHoraires[0];
        }

        $toDate = DateTime::createFromFormat('d/m/Y H:i', $datas['valid_to']);

        if ('31/12 23:59' === $fromDate->format('d/m H:i') && $fromDate->format('d/m/Y') === $toDate->format('d/m/Y')) {
            $horaires = null;
            $fromDate->setDate($fromDate->format('Y'), 1, 1);
        }

        //Prevents Reject::BAD_EVENT_DATE_INTERVAL
        $toDate->setTime(0, 0, 0);
        $fromDate->setTime(0, 0, 0);

        return [
            'external_id' => 'FS-' . $datas['merchant_product_id'],
            'date_debut' => $fromDate,
            'date_fin' => $toDate,
            'horaires' => $horaires,
            'source' => $datas['aw_deep_link'],
            'nom' => $datas['product_name'],
            'descriptif' => nl2br(trim(sprintf("%s\n\n%s", $datas['description'], $datas['product_short_description']))),
            'url' => $this->getImageUrl($datas['merchant_image_url']),
            'tarif' => sprintf('%s€', $datas['search_price']),
            'placeName' => $datas['custom_2'],
            'placePostalCode' => $datas['custom_4'],
            'placeCity' => $datas['venue_address'],
            'placeStreet' => \in_array($datas['custom_6'], ['.', '-', ''], true) ? null : $datas['custom_6'],
            'placeCountryName' => $datas['custom_3'],
            'latitude' => (float) $datas['latitude'],
            'longitude' => (float) $datas['longitude'],
        ];
    }

    private function getImageUrl(string $url)
    {
        return $this->cache->get('fnac.urls.' . md5($url), function () use ($url) {
            $imageUrl = str_replace('grand/', '600/', $url);
            $client = new Client();

            try {
                $client->request('HEAD', $imageUrl);

                return $imageUrl;
            } catch (RequestException $exception) {
                return $url;
            }
        });
    }

    public static function getParserName(): string
    {
        return 'Fnac Spectacles';
    }
}
