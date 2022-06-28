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
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use App\Repository\CountryRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Parsedown;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAgendaParser extends AbstractParser
{
    /**
     * @var int
     */
    private const EVENT_BATCH_SIZE = 50;

    private HttpClientInterface $client;

    public function __construct(
        LoggerInterface $logger,
        EventProducer $eventProducer,
        EventHandler $eventHandler,
        ReservationsHandler $reservationsHandler,
        private CountryRepository $countryRepository,
        private string $openAgendaKey,
    ) {
        parent::__construct($logger, $eventProducer, $eventHandler, $reservationsHandler);

        $this->client = HttpClient::create();
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Open Agenda';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        // Fetch event uids from public.opendatasoft.com
        $query = [
            'rows' => '-1',
            'select' => 'uid',
            'lang' => 'fr',
            'timezone' => 'UTC',
        ];

        if ($incremental) {
            $query['where'] = sprintf("updated_at >= '%s'", (new DateTime('yesterday'))->format('Y-m-d'));
        } else {
            $query['where'] = sprintf("date_end >= '%s'", date('Y-m-d'));
        }

        $url = 'https://public.opendatasoft.com/api/v2/catalog/datasets/evenements-publics-cibul/exports/json';

        $response = $this->client->request('GET', $url, ['query' => $query]);
        $eventIds = array_map(fn (array $data) => (int) $data['uid'], $response->toArray());
        $eventIds = array_filter($eventIds);

        $eventChunks = array_chunk($eventIds, self::EVENT_BATCH_SIZE);

        // Then fetch agenda uids from api.openagenda.com
        $responses = [];
        foreach ($eventChunks as $eventChunk) {
            $url = 'https://api.openagenda.com/v1/events';
            $responses[] = $this->client->request('GET', $url, [
                'query' => [
                    'key' => $this->openAgendaKey,
                    'uids' => $eventChunk,
                ],
            ]);
        }

        foreach ($this->client->stream($responses) as $response => $chunk) {
            try {
                if (!$chunk->isLast()) {
                    continue;
                }

                $datas = $response->toArray();
                if (true !== $datas['success']) {
                    $exception = new RuntimeException('Unable to fetch agenda ids from uids');
                    $this->logException($exception, $datas);
                    continue;
                }

                // Parse events
                $this->publishEvents($datas['data']);
            } catch (TransportExceptionInterface|HttpExceptionInterface $exception) {
                $this->logException($exception);
            }
        }
    }

    private function publishEvents(array $data): void
    {
        foreach ($data as $datum) {
            $dto = $this->arrayToDto($datum);
            if (null === $dto) {
                continue;
            }

            $this->publish($dto);
        }
    }

    private function arrayToDto(array $data): ?EventDto
    {
        if (empty($data['freeText']['fr'])) {
            return null;
        }

        if (empty($data['locations'])) {
            return null;
        }

        $location = current($data['locations']);
        if (empty($location['dates'])) {
            return null;
        }

        $countryCode = null;
        if (empty($location['countryCode'])) {
            $country = $this->countryRepository->getFromRegionOrDepartment($location['region'] ?? null, $location['department'] ?? null);
            $countryCode = $country?->getId();
        }

        if (null === $countryCode) {
            return null;
        }

        $dates = $location['dates'];
        $startDateAsArray = current($dates);
        $endDateAsArray = end($dates);
        $startDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $startDateAsArray['date'] . ' ' . $startDateAsArray['timeStart']);
        $endDate = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $endDateAsArray['date'] . ' ' . $endDateAsArray['timeEnd']);

        $hours = null;
        if ($startDate instanceof DateTimeInterface && $endDate instanceof DateTimeInterface && $startDate->getTimestamp() !== $endDate->getTimestamp()) {
            $hours = sprintf('De %s à %s', $startDate->format("H\hi"), $endDate->format("H\hi"));
        } elseif ($startDate instanceof DateTimeInterface) {
            $hours = sprintf('A %s', $startDate->format("H\hi"));
        }

        $mdParser = new Parsedown();
        $description = $mdParser->text($data['freeText']['fr']);

        $type = null;
        if (!empty($data['tags']['fr'])) {
            $type = $data['tags']['fr'];
        }

        if ($data['image'] && str_starts_with($data['image'], '//')) {
            $data['image'] = 'https:' . $data['image'];
        }

        $infos = $this->reservationsHandler->parseReservations($location['ticketLink'] ?? null);

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->name = $data['title']['fr'];
        $event->description = $description;
        $event->source = $data['link'];
        $event->externalId = sprintf('OA-%s', $data['uid']);
        $event->imageUrl = $data['image'];
        $event->externalUpdatedAt = new DateTimeImmutable($data['updatedAt']);
        $event->startDate = $startDate;
        $event->endDate = $endDate;
        $event->hours = $hours;
        $event->latitude = $location['latitude'];
        $event->longitude = $location['longitude'];
        $event->address = $location['address'];
        $event->type = $type;
        $event->websiteContacts = $infos['urls'];
        $event->phoneContacts = $infos['phones'];
        $event->emailContacts = $infos['emails'];

        $place = new PlaceDto();
        $place->name = $location['placename'] ?? null;
        $place->externalId = sprintf('OA-%s', $location['uid']);

        $city = new CityDto();
        $city->postalCode = $location['postalCode'] ?? null;
        $city->name = $location['city'] ?? null;

        $country = new CountryDto();
        $country->code = $countryCode;

        $city->country = $country;

        $place->country = $country;

        $place->city = $city;

        $event->place = $place;

        return $event;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'openagenda';
    }
}
