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
use App\Dto\EventDateTimeDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Handler\EventHandler;
use App\Producer\EventProducer;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FnacSpectaclesAwinParser extends AbstractAwinParser
{
    /**
     * @var string
     */
    private const DATAFEED_URL = 'https://productdata.awin.com/datafeed/download/apikey/%key%/language/fr/fid/23455/columns/aw_deep_link,product_name,aw_product_id,merchant_product_id,merchant_image_url,description,merchant_category,search_price,is_for_sale,custom_1,valid_to,product_short_description,custom_2,custom_4,custom_6,custom_3,Tickets%3Avenue_address,Tickets%3Alatitude,Tickets%3Alongitude/format/xml-tree/compression/gzip/';

    public function __construct(
        LoggerInterface $logger,
        EventProducer $eventProducer,
        EventHandler $eventHandler,
        HttpClientInterface $httpClient,
        string $tempPath,
        string $awinApiKey,
        private readonly CacheInterface $cache,
    ) {
        parent::__construct($logger, $eventProducer, $eventHandler, $httpClient, $tempPath, $awinApiKey);
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

        $startDates = array_filter(explode(';', (string) $data['custom_1']));

        if ([] === $startDates) {
            return null;
        }

        $validToDate = DateTimeImmutable::createFromFormat('d/m/Y H:i', $data['valid_to']);
        if (false === $validToDate) {
            return null;
        }

        // Create date/time slots for each start date
        $dateTimeDtos = [];
        foreach ($startDates as $startDateStr) {
            $startDate = DateTimeImmutable::createFromFormat('d/m/Y H:i', $startDateStr);
            if (false === $startDate) {
                continue;
            }

            // Each start date creates a slot that ends at 23:59 the same day
            $endDate = $startDate->setTime(23, 59, 59);

            // Special handling: New Year's Eve all-day events
            if ('31/12 23:59' === $startDate->format('d/m H:i')) {
                $startDate = $startDate->setDate((int) $startDate->format('Y'), 1, 1)->setTime(0, 0);
                $endDate = $startDate->setTime(23, 59, 59);
            }

            $dateTimeDtos[] = new EventDateTimeDto($startDate, $endDate);
        }

        if (empty($dateTimeDtos)) {
            return null;
        }

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $data['merchant_product_id'];
        $event->dateTimes = $dateTimeDtos;
        $event->source = $data['aw_deep_link'];
        $event->name = $data['product_name'];
        $event->description = nl2br(trim(\sprintf("%s\n\n%s", $data['description'], $data['product_short_description'])));
        $event->imageUrl = $this->getImageUrl($data['merchant_image_url']);
        $event->prices = \sprintf('%s€', $data['search_price']);
        $event->latitude = (float) $data['latitude'];
        $event->longitude = (float) $data['longitude'];

        $place = new PlaceDto();
        $place->name = $data['custom_2'];
        $place->street = \in_array($data['custom_6'], ['.', '-', ''], true) ? null : $data['custom_6'];
        $place->externalId = sha1(\sprintf(
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
