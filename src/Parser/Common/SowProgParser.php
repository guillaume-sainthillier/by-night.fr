<?php

namespace App\Parser\Common;

use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use function GuzzleHttp\Psr7\copy_to_string;

/**
 * @author Guillaume SAINTHILLIER
 */
class SowProgParser extends AbstractParser
{
    /** @var Client */
    private $client;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, string $sowprogUsername, string $sowprogPassword)
    {
        parent::__construct($logger, $eventProducer);

        $this->client = new Client([
            'base_uri' => 'https://agenda.sowprog.com',
            'auth' => [$sowprogUsername, $sowprogPassword],
            'headers' => [
                'accept' => 'application/json'
            ]
        ]);
    }

    public static function getParserName(): string
    {
        return 'Sow Prog';
    }

    public function parse(bool $incremental): void
    {
        $modifiedSince = true === $incremental ? 1000 * ((time() - 86400)) : 0;
        $response = $this->client->get('/rest/v1_2/scheduledEvents/search?modifiedSince=' . $modifiedSince);
        $events = json_decode(copy_to_string($response->getBody()), true);

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
            'url' => $event['event']['picture'],
            'external_updated_at' => (new \DateTime)->setTimestamp($event['modificationDate'] / 1000),
        ];

        $tab_infos['type_manifestation'] = $event['event']['eventType']['label'];
        $tab_infos['categorie_manifestation'] = $event['event']['eventStyle']['label'];

        $tab_infos['date_debut'] = new \DateTime($currentSchedule['date']);
        $tab_infos['date_fin'] = new \DateTime($currentSchedule['endDate']);

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
            $prices = array_map(function (array $price) {
                return sprintf('%s : %d%s', $price['label'], $price['price'], $price['currency'] === 'EUR' ? '€' : $price['currency']);
            }, $event['eventPrice']);
            $tab_infos['tarif'] = implode(' - ', $prices);
        }

        if (!empty($event['ticketStore'])) {
            $tickets = array_map(function (array $ticket) {
                return $ticket['url'];
            }, $event['ticketStore']);
            $tab_infos['reservation_internet'] = implode(' ', $tickets);
        }

        if ($event['location']) {
            $location = $event['location'];

            $tab_infos['placeName'] = $location['name'];
            $tab_infos['placeExternalId'] = 'SP-' . $location['id'];

            if ($location['contact']) {
                $contact = $event['location']['contact'];
                $tab_infos['latitude'] = (float)$contact['lattitude'];
                $tab_infos['longitude'] = (float)$contact['longitude'];

                $tab_infos['placeStreet'] = trim(sprintf('%s %s', $contact['addressLine1'], $contact['addressLine2']));
                $tab_infos['placePostalCode'] = $contact['zipCode'];
                $tab_infos['placeCity'] = $contact['city'];
                $tab_infos['placeCountryName'] = $contact['country'];
            }
        }

        return $tab_infos;
    }
}
