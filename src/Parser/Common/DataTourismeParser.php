<?php

/*
 * This file is part of By Night.
 * (c) Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use DateTime;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use GuzzleHttp\Client;
use function GuzzleHttp\Psr7\copy_to_string;
use JsonMachine\JsonMachine;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Cache\CacheInterface;

class DataTourismeParser extends AbstractParser
{
    private const INCREMENTAL_WEBSERVICE_FEED = 'https://diffuseur.datatourisme.gouv.fr/webservice/0b37dd2ac54a022db5eef44e88eee42c/%s';
    private const UPCOMING_WEBSERVICE_FEED = 'https://diffuseur.datatourisme.gouv.fr/webservice/0b226e3ced3583df970c753ab66e085f/%s';

    /** @var string */
    private $tempPath;

    /** @var string */
    private $dataTourismeAppKey;

    /** @var CacheInterface */
    private $cache;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, CacheInterface $dataTourismeCache, string $tempPath, string $dataTourismeAppKey)
    {
        parent::__construct($logger, $eventProducer);

        $this->tempPath = $tempPath;
        $this->dataTourismeAppKey = $dataTourismeAppKey;
        $this->cache = $dataTourismeCache;
    }

    public static function getParserName(): string
    {
        return 'Data Tourisme';
    }

    public function parse(bool $incremental): void
    {
        $url = $incremental ? self::INCREMENTAL_WEBSERVICE_FEED : self::UPCOMING_WEBSERVICE_FEED;
        $file = $this->getFeed(sprintf($url, $this->dataTourismeAppKey));
        $jsonStream = JsonMachine::fromFile($file, '/@graph');
        foreach ($jsonStream as $datas) {
            $events = array_filter($this->getInfoEvents($datas));

            foreach ($events as $event) {
                $this->publish($event);
            }
        }
    }

    private function getInfoEvents(array $datas): array
    {
        if (empty($datas['isLocatedAt']) || empty($datas['takesPlaceAt'])) {
            return [];
        }

        $datas['owl:topObjectProperty'] = $this->getResourceById($datas['owl:topObjectProperty'] ?? []);
        $datas['lastUpdate'] = $this->getResourceById($datas['lastUpdate']);
        $datas['lastUpdate'] = $this->getResourceById($datas['lastUpdate']);
        $datas['hasMainRepresentation'] = $this->getResourceById($datas['hasMainRepresentation'] ?? [], true);
        $datas['hasBeenCreatedBy'] = $this->getResourceById($datas['hasBeenCreatedBy'] ?? [], true);
        $datas['hasBeenPublishedBy'] = $this->getResourceById($datas['hasBeenPublishedBy'] ?? [], true);
        $datas['hasBookingContact'] = $this->getResourceById($datas['hasBookingContact'] ?? [], true);
        $datas['schema:offers'] = $this->getResourceById($datas['schema:offers'] ?? []);
        if (isset($datas['schema:offers']['schema:priceSpecification'])) {
            $datas['schema:offers']['schema:priceSpecification'] = $this->getResourceById($datas['schema:offers']['schema:priceSpecification']);
        }

        $datas['hasContact'] = $this->getResourceById($datas['hasContact'] ?? [], true);
        $datas['isLocatedAt'] = $this->getResourceById($datas['isLocatedAt']);
        if (isset($datas['isLocatedAt'][0]) && \is_array($datas['isLocatedAt'][0])) {
            $datas['isLocatedAt'] = $datas['isLocatedAt'][0];
        }

        if (isset($datas['isLocatedAt']['schema:address'][0]) && \is_array($datas['isLocatedAt']['schema:address'][0])) {
            $datas['isLocatedAt']['schema:address'] = $datas['isLocatedAt']['schema:address'][0];
        }

        if (isset($datas['isLocatedAt']['schema:address']['hasAddressCity'][0]) && \is_array($datas['isLocatedAt']['schema:address']['hasAddressCity'][0])) {
            $datas['isLocatedAt']['schema:address']['hasAddressCity'] = $datas['isLocatedAt']['schema:address']['hasAddressCity'][0];
        }

        if (isset($datas['isLocatedAt']['schema:address']['schema:addressLocality'][0]) && \is_array($datas['isLocatedAt']['schema:address']['schema:addressLocality'][0])) {
            $datas['isLocatedAt']['schema:address']['schema:addressLocality'] = $datas['isLocatedAt']['schema:address']['schema:addressLocality'][0];
        }

        $datas['takesPlaceAt'] = $this->getResourceById($datas['takesPlaceAt'], true);

        $events = [];
        $propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()->disableExceptionOnInvalidIndex()->getPropertyAccessor();

        $typesManifestation = [];
        foreach ($datas['@type'] as $type) {
            $typesManifestation[] = $this->getFrenchType($type);
        }
        $typesManifestation = array_filter(array_unique($typesManifestation));

        $categoriesManifestation = [];
        $datas['hasTheme'] = $datas['hasTheme'] ?? [];
        $datas['hasTheme'] = isset($datas['hasTheme'][0]) ? $datas['hasTheme'] : [$datas['hasTheme']];
        foreach ($datas['hasTheme'] as $theme) {
            if (!isset($theme['rdfs:label']['@value']) || \in_array($theme['rdfs:label']['@value'], $typesManifestation, true)) {
                continue;
            }
            $categoriesManifestation[] = $theme['rdfs:label']['@value'];
        }
        $categoriesManifestation = array_filter(array_unique($categoriesManifestation));

        $country = 'France';
        if (isset($datas['isLocatedAt']['schema:address']['hasAddressCity']['isPartOfDepartment']['isPartOfRegion']['isPartOfCountry']['rdfs:label']['@value'])) {
            $country = $datas['isLocatedAt']['schema:address']['hasAddressCity']['isPartOfDepartment']['isPartOfRegion']['isPartOfCountry']['rdfs:label']['@value'];
        }

        $latitude = null;
        $longitude = null;

        if (isset($datas['isLocatedAt']['schema:geo']['schema:latitude']['@value'])) {
            $latitude = (float) $datas['isLocatedAt']['schema:geo']['schema:latitude']['@value'];
        }

        if (isset($datas['isLocatedAt']['schema:geo']['schema:longitude']['@value'])) {
            $longitude = (float) $datas['isLocatedAt']['schema:geo']['schema:longitude']['@value'];
        }

        $emails = [];
        $phones = [];
        $websites = [];

        foreach (['hasBookingContact', 'hasContact'] as $key) {
            foreach ($datas[$key] as $currentDatas) {
                if (isset($currentDatas['schema:email'])) {
                    $emails = array_merge($emails, \is_array($currentDatas['schema:email']) ? $currentDatas['schema:email'] : [$currentDatas['schema:email']]);
                }

                if (isset($currentDatas['schema:telephone'])) {
                    $phones = array_merge($phones, \is_array($currentDatas['schema:telephone']) ? $currentDatas['schema:telephone'] : [$currentDatas['schema:telephone']]);
                }

                if (isset($currentDatas['foaf:homepage'])) {
                    $websites = array_merge($websites, \is_array($currentDatas['foaf:homepage']) ? $currentDatas['foaf:homepage'] : [$currentDatas['foaf:homepage']]);
                }
            }
        }

        $websites = array_filter(array_unique($websites));
        $phones = array_filter(array_unique($phones));
        $emails = array_filter(array_unique($emails));

        $updatedAt = new DateTime($datas['lastUpdate']['@value']);
        $updatedAt->setTime(0, 0, 0);

        if (\is_array($datas['isLocatedAt']['schema:address']['schema:addressLocality'])) {
            $datas['isLocatedAt']['schema:address']['schema:addressLocality'] = current($datas['isLocatedAt']['schema:address']['schema:addressLocality']);
        }

        if (isset($datas['rdfs:label'][0]) && \is_array($datas['rdfs:label'][0])) {
            $datas['rdfs:label'] = $datas['rdfs:label'][0];
        }

        if (isset($datas['owl:topObjectProperty']['dc:description'][0]) && \is_array($datas['owl:topObjectProperty']['dc:description'][0])) {
            $datas['owl:topObjectProperty']['dc:description'] = $datas['owl:topObjectProperty']['dc:description'][0];
        }

        $description = null;
        if (isset($datas['owl:topObjectProperty']['owl:topDataProperty']['@value'])) {
            $description = $datas['owl:topObjectProperty']['owl:topDataProperty']['@value'];
        } elseif (isset($datas['owl:topObjectProperty']['dc:description']['@value'])) {
            $description = $datas['owl:topObjectProperty']['dc:description']['@value'];
        } else {
            $description = $datas['rdfs:label']['@value'];
        }

        $url = $propertyAccessor->getValue($datas, '[hasMainRepresentation][ebucore:hasRelatedResource][ebucore:locator][@value]');
        if (!$url) {
            $url = $propertyAccessor->getValue($datas, '[hasMainRepresentation][0][ebucore:hasRelatedResource][ebucore:locator][@value]');
        }

        $event = [
            'external_updated_at' => $updatedAt,
            'nom' => $datas['rdfs:label']['@value'],
            'descriptif' => $description,
            'type_manifestation' => implode(', ', $typesManifestation) ?: null,
            'categorie_manifestation' => implode(', ', $categoriesManifestation) ?: null,
            'source' => $datas['@id'],
            'placeName' => $datas['isLocatedAt']['schema:address']['schema:addressLocality'],
            'placeCity' => $datas['isLocatedAt']['schema:address']['schema:addressLocality'],
            'placeStreet' => $propertyAccessor->getValue($datas, '[isLocatedAt][schema:address][schema:streetAddress]'),
            'placePostalCode' => $datas['isLocatedAt']['schema:address']['schema:postalCode'],
            'placeExternalId' => 'DT-' . $datas['isLocatedAt']['@id'],
            'placeCountryName' => $country,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'url' => $url,
            'reservation_internet' => implode(' ', $websites) ?: null,
            'reservation_email' => implode(' ', $emails) ?: null,
            'reservation_telephone' => implode(' ', $phones) ?: null,
        ];

        if (\is_array($event['placeStreet'])) {
            $event['placeStreet'] = end($event['placeStreet']);
        }

        //Multiple date handling
        foreach ($datas['takesPlaceAt'] as $date) {
            if (!isset($date['endDate'])) {
                continue;
            }
            $from = new DateTime($date['startDate']['@value']);
            $to = new DateTime($date['endDate']['@value']);
            $horaires = null;

            $startTime = $propertyAccessor->getValue($date, '[startTime][@value]');
            $endTime = $propertyAccessor->getValue($date, '[endTime][@value]');

            if ($startTime && $endTime) {
                $startTime = preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $startTime);
                $endTime = preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $endTime);
                $horaires = sprintf('De %s à %s', $startTime, $endTime);
            } elseif ($startTime) {
                $startTime = preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $startTime);
                $horaires = sprintf('À %s', $startTime);
            }

            $event += [
                'external_id' => 'DT-' . $datas['dc:identifier'] . '-' . $date['@id'],
                'date_debut' => $from,
                'date_fin' => $to,
                'horaires' => $horaires,
            ];
            $events[] = $event;
        }

        return $events;
    }

    private function getResourceById(array $resource, bool $alwaysReturnList = false): array
    {
        if ($alwaysReturnList && !isset($resource[0])) {
            return $this->getResourceById([$resource]);
        }

        if (isset($resource[0]) && \is_array($resource[0])) {
            foreach ($resource as $key => $value) {
                $resource[$key] = $this->getResourceById($value);
            }

            return $resource;
        }

        if (!isset($resource['@id'])) {
            return $resource;
        }

        $key = str_replace(':', '', $resource['@id']);
        $resource = array_merge($resource, $this->cache->get($key, function () use ($resource) {
            return $resource;
        }));

        foreach ($resource as $key => $value) {
            if (\is_array($value)) {
                $resource[$key] = $this->getResourceById($value);
            }
        }

        return $resource;
    }

    private function getFeed(string $url): string
    {
        $filePath = $this->tempPath . \DIRECTORY_SEPARATOR . sprintf('%s.jsonld', md5($url));

        $client = new Client();
        $response = $client->request('GET', $url);
        file_put_contents($filePath, copy_to_string($response->getBody()));

        return $filePath;
    }

    private function getFrenchType(string $type): ?string
    {
        $mapping = [
            'schema:BusinessEvent' => 'Business',
            'schema:ChildrensEvent' => 'Famille',
            'schema:ComedyEvent' => 'Spectacle',
            'schema:CourseInstance' => 'Cours',
            'schema:DanceEvent' => 'Dance',
            //'schema:DeliveryEvent' => 'DeliveryEvent',
            'schema:EducationEvent' => 'Famille',
            //'schema:EventSeries' => 'Exposition',
            'schema:ExhibitionEvent' => 'ExhibitionEvent',
            'schema:Festival' => 'Concert, Musique',
            'schema:FoodEvent' => 'Nourriture',
            'schema:LiteraryEvent' => 'Littérature',
            'schema:MusicEvent' => 'Musique',
            'schema:PublicationEvent' => 'Recherche',
            'schema:BroadcastEvent' => 'Radio',
            //'schema:OnDemandEvent' => 'OnDemandEvent',
            'schema:SaleEvent' => 'Commerce',
            //'schema:ScreeningEvent' => 'ScreeningEvent',
            'schema:SocialEvent' => 'Communautaire',
            'schema:SportsEvent' => 'Sport',
            'schema:TheaterEvent' => 'Spectacle',
            'schema:VisualArtsEvent' => 'Art',
            'ChildrensEvent' => 'Famille',
            'CulturalEvent' => 'Culture',
            'Festival' => 'Concert, Musique',
            'Concert' => 'Concert',
            'Theater' => 'Théâtre',
            'TheaterEvent' => 'Théâtre',
            'Exhibition' => 'Exposition',
            'GarageSale' => 'Brocante',
            'SportsEvent' => 'Sport',
            'SportsCompetition' => 'Compétition',
        ];

        return $mapping[$type] ?? null;
    }
}
