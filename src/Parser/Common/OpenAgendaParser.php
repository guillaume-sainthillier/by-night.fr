<?php

/*
 * This file is part of By Night.
 * (c) 2013-2020 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Handler\ReservationsHandler;
use RuntimeException;
use Parsedown;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use App\Repository\CountryRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\NonUniqueResultException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenAgendaParser extends AbstractParser
{
    private const EVENT_BATCH_SIZE = 50;

    private HttpClientInterface $client;
    private CountryRepository $countryRepository;

    private string $openAgendaKey;

    public function __construct(string $openAgendaKey, LoggerInterface $logger, EventProducer $eventProducer, ReservationsHandler $reservationsHandler, CountryRepository $countryRepository)
    {
        parent::__construct($logger, $eventProducer, $reservationsHandler);

        $this->countryRepository = $countryRepository;
        $this->openAgendaKey = $openAgendaKey;
        $this->client = HttpClient::create();
    }

    public static function getParserName(): string
    {
        return 'Open Agenda';
    }

    public function parse(bool $incremental): void
    {
        //Fetch event uids from public.opendatasoft.com
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

        //Then fetch agenda uids from api.openagenda.com
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

                //Parse events
                $this->publishEvents($datas['data']);
            } catch (TransportExceptionInterface | HttpExceptionInterface $exception) {
                $this->logException($exception);
            }
        }
    }

    private function publishEvents(array $events): void
    {
        foreach ($events as $event) {
            $event = $this->getInfoEvent($event);
            if (null === $event) {
                continue;
            }
            $this->publish($event);
        }
    }

    private function getInfoEvent(array $event): ?array
    {
        if (empty($event['freeText']['fr'])) {
            return null;
        }

        if (empty($event['locations'])) {
            return null;
        }

        $location = current($event['locations']);
        if (empty($location['dates'])) {
            return null;
        }

        $countryCode = null;
        if (empty($location['countryCode'])) {
            try {
                $country = $this->countryRepository->getFromRegionOrDepartment($location['region'] ?? null, $location['department'] ?? null);
                $countryCode = $country !== null ? $country->getId() : null;
            } catch (NonUniqueResultException $exception) {
                return null;
            }
        }

        if (null === $countryCode) {
            return null;
        }

        $dates = $location['dates'];
        $startDateAsArray = current($dates);
        $endDateAsArray = end($dates);
        $dateDebut = DateTime::createFromFormat('Y-m-d H:i:s', $startDateAsArray['date'] . ' ' . $startDateAsArray['timeStart']);
        $dateFin = DateTime::createFromFormat('Y-m-d H:i:s', $endDateAsArray['date'] . ' ' . $endDateAsArray['timeEnd']);

        $horaires = null;
        if ($dateDebut instanceof DateTimeInterface && $dateFin instanceof DateTimeInterface && $dateDebut->getTimestamp() !== $dateFin->getTimestamp()) {
            $horaires = \sprintf('De %s à %s', $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
        } elseif ($dateDebut instanceof DateTimeInterface) {
            $horaires = \sprintf('A %s', $dateDebut->format("H\hi"));
        }

        $mdParser = new Parsedown();
        $description = $mdParser->text($event['freeText']['fr']);

        $type_manifestation = null;
        if (!empty($event['tags']['fr'])) {
            $type_manifestation = $event['tags']['fr'];
        }

        if ($event['image'] && 0 === strpos($event['image'], '//')) {
            $event['image'] = 'https:' . $event['image'];
        }

        $infos = $this->reservationsHandler->parseReservations($location['ticketLink'] ?? null);

        return [
            'nom' => $event['title']['fr'],
            'descriptif' => $description,
            'source' => $event['link'],
            'external_id' => 'OA-' . $event['uid'],
            'url' => $event['image'],
            'external_updated_at' => new DateTime($event['updatedAt']),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'horaires' => $horaires,
            'latitude' => (float) $location['latitude'],
            'longitude' => (float) $location['longitude'],
            'adresse' => $location['address'] ?? null,
            'placeName' => $location['placename'] ?? null,
            'placePostalCode' => $location['postalCode'] ?? null,
            'placeCity' => $location['city'] ?? null,
            'placeCountryName' => $countryCode,
            'placeExternalId' => 'OA-' . $location['uid'],
            'type_manifestation' => $type_manifestation,
            'websiteContacts' => $infos['urls'],
            'phoneContacts' => $infos['phones'],
            'mailContacts' => $infos['emails'],
        ];
    }
}
