<?php

namespace App\Parser\Toulouse;

use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * @author Guillaume SAINTHILLIER
 */
class BikiniParser extends AbstractParser
{
    private const EVENTS_URL = 'https://lebikini.com/events.json';

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer)
    {
        parent::__construct($logger, $eventProducer);
    }

    public function parse(bool $incremental): void
    {
        //Récupère les différents liens à parser depuis le flux RSS
        $datas = json_decode(file_get_contents(self::EVENTS_URL), true);

        foreach ($datas['events'] as $event) {
            $event = $this->getInfosEvent($event);
            $this->publish($event);
        }
    }

    private function getInfosEvent(array $event): array
    {
        $from = DateTime::createFromFormat('U', $event['startTime']);
        $to = DateTime::createFromFormat('U', $event['endTime']);

        $horaires = null;
        if ($from->getTimestamp() === $to->getTimestamp()) {
            $horaires = sprintf('À %s', $from->format('H:i'));
        } else {
            $horaires = sprintf('De %s à %s', $from->format('H:i'), $to->format('H:i'));
        }

        $tab_retour = [
            'external_id' => 'BKN-' . $event['id'],
            'nom' => $event['title'],
            'date_debut' => $from,
            'date_fin' => $to,
            'horaires' => $horaires,
            'descriptif' => $event['htmlDescription'],
            'type_manifestation' => 'Concert, Musique',
            'categorie_manifestation' => $event['style'],
            'source' => $event['url'],
            'reservation_internet' => $event['ticketUrl'],
            'url' => $event['image'],
            'modification_derniere_minute' => $event['status'] === 'postponed' ? 'REPORTE' : null,
            'placeName' => $event['place']['name'],
            'placeCountryName' => 'FR',
            'tarif' => implode(' // ', $event['prices']),
        ];

        $placeParts = explode("\n", $event['place']['address']);
        $placeParts = array_map('trim', $placeParts);

        if (count($placeParts) >= 2) {
            if (preg_match("#^(\d+) (.+)$#", end($placeParts), $postalCodeAndCity)) {
                $tab_retour['placePostalCode'] = $postalCodeAndCity[1];
                $tab_retour['placeCity'] = $postalCodeAndCity[2];
            }

            //Skip first informations (i.e Parc Technologique du Canal)
            if (count($placeParts) === 3) {
                $tab_retour['placeStreet'] = $placeParts[1];
            } else {
                $tab_retour['placeStreet'] = $placeParts[0];
            }
        }

        return $tab_retour;
    }

    public static function getParserName(): string
    {
        return 'Bikini';
    }
}
