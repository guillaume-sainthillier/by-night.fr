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
use App\Handler\EventHandler;
use App\Handler\ReservationsHandler;
use App\Producer\EventProducer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FnacSpectaclesAwinParser extends AbstractAwinParser
{
    /**
     * @var string
     */
    private const DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/language/fr/fid/23455/columns/aw_deep_link,product_name,aw_product_id,merchant_product_id,merchant_image_url,description,merchant_category,search_price,is_for_sale,custom_1,valid_to,product_short_description,custom_2,custom_4,custom_6,custom_3,Tickets%3Avenue_address,Tickets%3Alatitude,Tickets%3Alongitude/format/xml-tree/compression/gzip/';

    public function __construct(
        LoggerInterface $logger,
        EventProducer $eventProducer,
        EventHandler $eventHandler,
        ReservationsHandler $reservationsHandler,
        HttpClientInterface $httpClient,
        string $tempPath,
        string $awinApiKey,
        private CacheInterface $cache
    ) {
        parent::__construct($logger, $eventProducer, $eventHandler, $reservationsHandler, $httpClient, $tempPath, $awinApiKey);
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
     * {@inheritDoc}
     */
    protected function arrayToDto(array $data): ?EventDto
    {
        if ('0' === $data['is_for_sale'] || '' === trim($data['custom_2'] ?? '')) {
            return null;
        }

        $seenHours = [];
        $hours = null;
        $startDate = null;
        $startDates = array_filter(explode(';', $data['custom_1']));

        if ([] === $startDates) {
            return null;
        }

        foreach ($startDates as $startDateStr) {
            $startDate = \DateTimeImmutable::createFromFormat('d/m/Y H:i', $startDateStr);
            if (false !== $startDate) {
                $seenHours[] = sprintf('À %s', $startDate->format('H\hi'));
            }
        }

        if ([] === $seenHours) {
            return null;
        }

        $seenHours = array_unique($seenHours);

        if (1 === \count($seenHours)) {
            $hours = $seenHours[0];
        }

        $endDate = \DateTimeImmutable::createFromFormat('d/m/Y H:i', $data['valid_to']);

        if ('31/12 23:59' === $startDate->format('d/m H:i') && $startDate->format('d/m/Y') === $endDate->format('d/m/Y')) {
            $hours = null;
            $startDate->setDate((int) $startDate->format('Y'), 1, 1);
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
        $event->description = nl2br(trim(sprintf("%s\n\n%s", $data['description'], $data['product_short_description'])));
        $event->imageUrl = $this->getImageUrl($data['merchant_image_url']);
        $event->prices = sprintf('%s€', $data['search_price']);
        $event->latitude = (float) $data['latitude'];
        $event->longitude = (float) $data['longitude'];

        $place = new PlaceDto();
        $place->name = $data['custom_2'];
        $place->street = \in_array($data['custom_6'], ['.', '-', ''], true) ? null : $data['custom_6'];
        $place->externalId = sha1(sprintf(
            '%s %s %s %s %s',
            $data['custom_2'],
            $data['custom_6'],
            $data['venue_address'],
            $data['custom_4'],
            $data['custom_3'],
        ));

        $city = new CityDto();
        $city->name = $data['venue_address'];
        $city->postalCode = $data['custom_4'];

        $country = new CountryDto();
        $country->name = $data['custom_3'];

        $city->country = $country;

        $place->country = $country;

        $place->city = $city;

        $event->place = $place;

        return $event;
    }

    private function getImageUrl(string $url)
    {
        return $this->cache->get('fnac.urls.' . md5($url), static function () use ($url) {
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
