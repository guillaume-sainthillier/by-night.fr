<?php

/*
 * This file is part of By Night.
 * (c) 2013-2022 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Handler\ReservationsHandler;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use DateTime;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SowProgParser extends AbstractParser
{
    private HttpClientInterface $client;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, ReservationsHandler $reservationsHandler, string $sowprogUsername, string $sowprogPassword)
    {
        parent::__construct($logger, $eventProducer, $reservationsHandler);

        $this->client = HttpClient::create([
            'base_uri' => 'https://agenda.sowprog.com',
            'auth_basic' => [$sowprogUsername, $sowprogPassword],
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Sow Prog';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        $modifiedSince = $incremental ? 1_000 * ((time() - 86_400)) : 0;
        $response = $this->client->request('GET', '/rest/v1_2/scheduledEvents?modifiedSince=' . $modifiedSince);
        $events = $response->toArray();

        foreach ($events['eventDescription'] as $event) {
            foreach ($event['eventSchedule']['eventScheduleDate'] as $schedule) {
                $the_event = $this->getInfoEvent($event, $schedule);
                $this->publish($the_event);
            }
        }
    }

    private function getInfoEvent(array $event, array $currentSchedule): array
    {
        $tab_infos = [
            'nom' => $event['event']['title'],
            'descriptif' => $event['event']['description'],
            'source' => 'http://www.sowprog.com/',
            'external_id' => 'SP-' . $event['id'] . '-' . $currentSchedule['id'],
            'url' => $event['event']['picture'] ?? null,
            'external_updated_at' => (new DateTime())->setTimestamp($event['modificationDate'] / 1_000),
        ];

        $tab_infos['type_manifestation'] = $event['event']['eventType']['label'];
        $tab_infos['categorie_manifestation'] = $event['event']['eventStyle']['label'];

        $tab_infos['date_debut'] = new DateTime($currentSchedule['date']);
        $tab_infos['date_fin'] = new DateTime($currentSchedule['endDate']);

        if ($currentSchedule['startHour'] && $currentSchedule['startHour'] !== $currentSchedule['endHour']) {
            $tab_infos['horaires'] = sprintf(
                'De %s à %s',
                str_replace(':', 'h', $currentSchedule['startHour']),
                str_replace(':', 'h', $currentSchedule['endHour'])
            );
        } elseif ($currentSchedule['startHour']) {
            $tab_infos['horaires'] = sprintf(
                'À %s',
                str_replace(':', 'h', $currentSchedule['startHour'])
            );
        }

        foreach ($event['artist'] as $artist) {
            $tab_infos['descriptif'] .= sprintf(
                "\n<h2>%s</h2>\n%s",
                $artist['name'],
                $artist['description']
            );
        }

        if (!empty($event['eventPrice'])) {
            $prices = array_map(fn (array $price) => sprintf('%s : %d%s', $price['label'], $price['price'], 'EUR' === $price['currency'] ? '€' : $price['currency']), $event['eventPrice']);
            $tab_infos['tarif'] = implode(' - ', $prices);
        }

        if (!empty($event['ticketStore'])) {
            $tickets = array_map(fn (array $ticket) => $ticket['url'], $event['ticketStore']);
            $tab_infos['websiteContacts'] = $tickets;
        }

        if ($event['location']) {
            $location = $event['location'];

            $tab_infos['placeName'] = $location['name'];
            $tab_infos['placeExternalId'] = 'SP-' . $location['id'];

            if ($location['contact']) {
                $contact = $event['location']['contact'];
                $tab_infos['latitude'] = (float) $contact['lattitude'];
                $tab_infos['longitude'] = (float) $contact['longitude'];

                $tab_infos['placeStreet'] = trim(sprintf('%s %s', $contact['addressLine1'], $contact['addressLine2']));
                $tab_infos['placePostalCode'] = $contact['zipCode'];
                $tab_infos['placeCity'] = $contact['city'];
                $tab_infos['placeCountryName'] = $contact['country'];
            }
        }

        return $tab_infos;
    }
}
