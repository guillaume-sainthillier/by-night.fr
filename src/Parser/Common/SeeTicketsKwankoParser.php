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
use App\Enum\EventStatus;
use App\Handler\EventHandler;
use App\Parser\AbstractParser;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class SeeTicketsKwankoParser extends AbstractParser
{
    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'KWANKO_SEETICKETS_FEED_URL')]
        private readonly string $feedUrl,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler);
    }

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
    #[Override]
    public function getCommandName(): string
    {
        return 'kwanko.seetickets';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function parse(bool $incremental): void
    {
        $path = $this->downloadCsv();

        try {
            $this->parseCsv($path);
        } finally {
            new Filesystem()->remove($path);
        }
    }

    private function downloadCsv(): string
    {
        $response = $this->httpClient->request('GET', $this->feedUrl);

        $path = \sprintf('%s/kwanko_seetickets_%s.csv', sys_get_temp_dir(), md5($this->feedUrl));
        $handle = fopen($path, 'w');
        if (false === $handle) {
            throw new RuntimeException(\sprintf('Unable to open file for writing: %s', $path));
        }

        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($handle, $chunk->getContent());
        }

        fclose($handle);

        return $path;
    }

    private function parseCsv(string $path): void
    {
        $handle = fopen($path, 'r');
        if (false === $handle) {
            throw new RuntimeException(\sprintf('Unable to open CSV file: %s', $path));
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (false === $headers) {
                throw new RuntimeException('Unable to read CSV headers');
            }

            while (false !== ($row = fgetcsv($handle, 0, ',', '"', ''))) {
                if (\count($row) !== \count($headers)) {
                    continue;
                }

                $data = array_combine($headers, $row);

                try {
                    $event = $this->arrayToDto($data);
                    if (null !== $event) {
                        $this->publish($event);
                    }
                } catch (Throwable $e) {
                    $this->logException($e, ['data' => $data]);
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, string> $data
     */
    private function arrayToDto(array $data): ?EventDto
    {
        $venueName = trim($data['venue_name'] ?? '');
        if ('' === $venueName) {
            return null;
        }

        $startDate = DateTimeImmutable::createFromFormat('d/m/Y H:i', $data['eventDate'] ?? '');
        if (false === $startDate) {
            return null;
        }

        // Format hours as "À HHhMM"
        $hours = \sprintf('À %s', $startDate->format('H\hi'));

        // Normalize date to midnight for date-only comparison
        $dateOnly = $startDate->setTime(0, 0);

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $data['pid'] ?? '';
        $event->name = $data['event_name'] ?? $data['name'] ?? '';
        $event->description = nl2br($data['desc'] ?? '');
        $event->type = $data['genre'] ?? null;
        $event->imageUrl = $data['imgurl'] ?? null;
        $event->source = $data['purl'] ?? null;
        $event->startDate = $dateOnly;
        $event->endDate = $dateOnly;
        $event->hours = $hours;
        $event->latitude = (float) ($data['latitude'] ?? 0);
        $event->longitude = (float) ($data['longitude'] ?? 0);
        $event->status = EventStatus::fromStatusMessage($data['onsale'] ?? null);

        // Build prices string
        $minPrice = $data['min_price'] ?? '';
        $maxPrice = $data['max_price'] ?? '';
        if ('' !== $minPrice && '' !== $maxPrice && $minPrice !== $maxPrice) {
            $event->prices = \sprintf('De %s€ à %s€', $minPrice, $maxPrice);
        } elseif ('' !== $minPrice) {
            $event->prices = \sprintf('%s€', $minPrice);
        } elseif ('' !== $maxPrice) {
            $event->prices = \sprintf('%s€', $maxPrice);
        }

        // Parse address: extract postal code and street
        $venueAddress = trim($data['venue_address'] ?? '');
        $street = null;
        $postalCode = null;
        if ('' !== $venueAddress && preg_match('/(\d{5})/', $venueAddress, $matches)) {
            $postalCode = $matches[1];
            $street = trim(substr($venueAddress, 0, (int) strpos($venueAddress, $postalCode)));
            if ('' === $street) {
                $street = null;
            }
        } elseif ('' !== $venueAddress) {
            $street = $venueAddress;
        }

        $place = new PlaceDto();
        $place->name = $venueName;
        $place->street = $street;
        $place->externalId = sha1(\sprintf(
            '%s %s %s',
            $venueName,
            $venueAddress,
            $data['event_location_city'] ?? '',
        ));

        $city = new CityDto();
        $city->name = $data['event_location_city'] ?? '';
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
