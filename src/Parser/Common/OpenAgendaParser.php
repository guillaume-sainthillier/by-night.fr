<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use RuntimeException;
use DateTime;
use DateTimeInterface;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use function GuzzleHttp\Psr7\copy_to_string;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class OpenAgendaParser extends AbstractParser
{
    // Next step : 'https://public.opendatasoft.com/api/v2/catalog/datasets/evenements-publics-cibul/records'
    private const AGENDA_IDS = [
        93184572, // https://openagenda.com/fetedelascience2019_hautsdefrance?lang=fr
        49405812, // https://openagenda.com/saison-culturelle-en-france?lang=fr
        7430297, // https://openagenda.com/agenda-culturel-grand-est?lang=fr
        1108324, // https://openagenda.com/un-air-de-bordeaux?lang=fr
        92445297, // https://openagenda.com/fetedelascience2019_occitanie?lang=fr
        13613180, // https://openagenda.com/grand-chatellerault?lang=fr
        87948516, // https://openagenda.com/agenda-different-seine-maritime?lang=fr
        93184572, // https://openagenda.com/fetedelascience2019_hautsdefrance?lang=fr
        41148947, // https://openagenda.com/terres-de-montaigu?lang=fr
        22126321, // https://openagenda.com/tootsweet?lang=fr
        43896350, // https://openagenda.com/iledefrance?lang=fr
        88167337, // https://openagenda.com/mediatheque-bibliotheques-st-denis-reunion?lang=fr
        69653526, // https://openagenda.com/france-numerique?lang=fr
        89904399, // https://openagenda.com/metropole-europeenne-de-lille?lang=fr
    ];

    private const EVENT_BATCH_SIZE = 300;

    /** @var Client */
    private $client;

    /** @var array */
    private $cache;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer)
    {
        parent::__construct($logger, $eventProducer);

        $this->client = new Client();
        $this->cache = [];
    }

    public static function getParserName(): string
    {
        return 'Open Agenda';
    }

    public function parse(bool $incremental): void
    {
        foreach (self::AGENDA_IDS as $id) {
            $this->makeRequest($id)->wait();
        }
    }

    private function makeRequest(int $agendaId, int $page = 0): PromiseInterface
    {
        //Send first request to get events size
        return $this->sendRequest($agendaId, $page)
            ->then(function (array $result) use ($agendaId, $page) {
                if (!isset($result['events'])) {
                    $exception = new RuntimeException(sprintf("Unable to find events for agenda '%s'", $agendaId));
                    $this->logException($exception, ['agendaId' => $agendaId, 'page' => $page]);

                    return new FulfilledPromise(null);
                }
                $this->publishEvents($result['events']);

                $nbPages = ceil($result['total'] / self::EVENT_BATCH_SIZE);
                if ($nbPages > 1) {
                    //Send next requests
                    $requests = function ($nbPages) use ($agendaId) {
                        for ($page = 1; $page <= $nbPages - 1; ++$page) {
                            yield function () use ($agendaId, $page) {
                                return $this
                                    ->sendRequest($agendaId, $page)
                                    ->then(function (array $results) {
                                        $this->publishEvents($results['events']);
                                    });
                            };
                        }
                    };

                    $pool = new Pool($this->client, $requests($nbPages), [
                        'concurrency' => 5,
                    ]);

                    return $pool->promise();
                }

                return new FulfilledPromise(null);
            });
    }

    private function sendRequest(int $agendaId, int $page): PromiseInterface
    {
        //https://openagenda.zendesk.com/hc/fr/articles/203034982-L-export-JSON-d-un-agenda
        $uri = sprintf('https://openagenda.com/agendas/%s/events.json?oaq[lang]=fr&limit=%d&offset=%d', $agendaId, self::EVENT_BATCH_SIZE, $page * self::EVENT_BATCH_SIZE);

        return $this->client
            ->getAsync($uri)
            ->then(function (ResponseInterface $result) {
                return json_decode(copy_to_string($result->getBody()), true, 512, JSON_THROW_ON_ERROR);
            });
    }

    private function publishEvents(array $events): int
    {
        $nbParsed = 0;
        foreach ($events as $event) {
            if (!empty($this->cache['visited'][$event['uid']])) {
                continue;
            }

            $this->cache['visited'][$event['uid']] = true;
            $event = $this->getInfoEvent($event);
            $this->publish($event);
            ++$nbParsed;
        }

        return $nbParsed;
    }

    private function getInfoEvent(array $event): array
    {
        $dateDebut = DateTime::createFromFormat('Y-m-d H:i', $event['firstDate'] . ' ' . $event['firstTimeStart']);
        $dateFin = DateTime::createFromFormat('Y-m-d H:i', $event['lastDate'] . ' ' . $event['lastTimeEnd']);

        $horaires = null;
        if ($dateDebut instanceof DateTimeInterface && $dateFin instanceof DateTimeInterface && $dateDebut->getTimestamp() !== $dateFin->getTimestamp()) {
            $horaires = \sprintf('De %s à %s', $dateDebut->format("H\hi"), $dateFin->format("H\hi"));
        } elseif ($dateDebut instanceof DateTimeInterface) {
            $horaires = \sprintf('A %s', $dateDebut->format("H\hi"));
        }

        $description = null;
        if (isset($event['html']['fr'])) {
            $description = $event['html']['fr'];
        } elseif (isset($event['longDescription']['fr'])) {
            $description = nl2br($event['longDescription']['fr']);
        } elseif (isset($event['description']['fr'])) {
            $description = nl2br($event['description']['fr']);
        }

        $type_manifestation = [];
        foreach ($event['tags'] as $tag) {
            $type_manifestation[] = $tag['label'];
        }

        return [
            'nom' => $event['title']['fr'],
            'descriptif' => $description,
            'source' => $event['canonicalUrl'],
            'external_id' => 'OA-' . $event['uid'],
            'url' => $event['originalImage'],
            'external_updated_at' => new DateTime($event['updatedAt']),
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'horaires' => $horaires,
            'latitude' => $event['latitude'],
            'longitude' => $event['longitude'],
            'adresse' => $event['address'],
            'placeStreet' => str_replace($event['postalCode'] . ' ' . $event['city'], '', $event['address']),
            'placeName' => $event['locationName'],
            'placePostalCode' => $event['postalCode'],
            'placeCity' => $event['city'],
            'placeCountryName' => $event['location']['countryCode'],
            'placeExternalId' => $event['locationUid'],
            'type_manifestation' => implode(', ', $type_manifestation) ?: null,
        ];
    }
}
