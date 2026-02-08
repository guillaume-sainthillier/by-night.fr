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
use App\Handler\EventHandler;
use DateTimeImmutable;
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

        // Parse end date from valid_to (YYYY-mm-dd format)
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d', $data['valid_to']);
        if (false === $endDate) {
            $endDate = $startDate;
        }

        // Parse hours from custom_7 (HH:mm format)
        $hours = null;
        $eventTime = trim($data['custom_7'] ?? '');
        if ('' !== $eventTime) {
            $hours = \sprintf('À %s', str_replace(':', 'h', $eventTime));
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
