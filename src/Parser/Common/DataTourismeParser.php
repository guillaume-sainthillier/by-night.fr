<?php

/*
 * This file is part of By Night.
 * (c) 2013-2021 Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Parser\Common;

use App\Dto\CityDto;
use App\Dto\CountryDto;
use App\Dto\EventDto;
use App\Dto\PlaceDto;
use App\Handler\ReservationsHandler;
use App\Parser\AbstractParser;
use App\Producer\EventProducer;
use DateTimeImmutable;
use const DIRECTORY_SEPARATOR;
use const JSON_THROW_ON_ERROR;
use const PHP_URL_PATH;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\PropertyAccess\Exception\AccessException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use ZipArchive;

class DataTourismeParser extends AbstractParser
{
    private const UUID_REGEX = '#^[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}$#';
    private const INCREMENTAL_WEBSERVICE_FEED = 'https://diffuseur.datatourisme.gouv.fr/webservice/0b37dd2ac54a022db5eef44e88eee42c/%s';
    private const UPCOMING_WEBSERVICE_FEED = 'https://diffuseur.datatourisme.gouv.fr/webservice/0b226e3ced3583df970c753ab66e085f/%s';

    private string $tempPath;

    private string $dataTourismeAppKey;

    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(LoggerInterface $logger, EventProducer $eventProducer, ReservationsHandler $reservationsHandler, string $tempPath, string $dataTourismeAppKey)
    {
        parent::__construct($logger, $eventProducer, $reservationsHandler);

        $this->tempPath = $tempPath;
        $this->dataTourismeAppKey = $dataTourismeAppKey;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableExceptionOnInvalidIndex()
            ->enableExceptionOnInvalidPropertyPath()
            ->getPropertyAccessor();
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserName(): string
    {
        return 'Data Tourisme';
    }

    /**
     * {@inheritDoc}
     */
    public static function getParserVersion(): string
    {
        return '1.2';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(bool $incremental): void
    {
        $url = $incremental ? self::INCREMENTAL_WEBSERVICE_FEED : self::UPCOMING_WEBSERVICE_FEED;
        $directory = $this->getFeed(sprintf($url, $this->dataTourismeAppKey));

        $finder = new Finder();
        $files = $finder
            ->files()
            ->name('*.json')
            ->in($directory)
            ->depth('> 0');

        $fs = new Filesystem();
        foreach ($files as $file) {
            $datas = json_decode(file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
            $dtos = array_filter($this->arrayToDtos($datas));

            foreach ($dtos as $dto) {
                $this->publish($dto);
            }
            $fs->remove($file->getPathname());
        }
    }

    private function getFeed(string $url): string
    {
        //Remove previous extracts
        $fs = new Filesystem();

        $filePath = $this->tempPath . DIRECTORY_SEPARATOR . sprintf('%s.zip', md5($url));
        $extractDirectory = $this->tempPath . DIRECTORY_SEPARATOR . md5($url);

        if ($fs->exists($extractDirectory)) {
            $fs->remove($extractDirectory);
        }

        //Download fresh version
        $client = HttpClient::create();
        $response = $client->request('GET', $url);

        $fileHandler = fopen($filePath, 'w');
        foreach ($client->stream($response) as $chunk) {
            fwrite($fileHandler, $chunk->getContent());
        }

        //Extract zip
        $zip = new ZipArchive();
        $res = $zip->open($filePath);
        if (true !== $res) {
            throw new RuntimeException(sprintf('Unable to unzip "%s": "%d" error code', $filePath, $res));
        }

        $zip->extractTo($extractDirectory);
        $zip->close();

        $fs->remove($filePath);

        return $extractDirectory;
    }

    private function arrayToDtos(array $datas): array
    {
        if (empty($datas['isLocatedAt']) || empty($datas['takesPlaceAt'])) {
            return [];
        }

        $datas['hasTheme'] ??= [];
        $datas['hasBookingContact'] ??= [];
        $datas['hasContact'] ??= [];

        $events = [];

        $typesManifestation = [];
        foreach ($datas['@type'] as $type) {
            $typesManifestation[] = $this->getFrenchType($type);
        }
        $typesManifestation = array_filter(array_unique($typesManifestation));

        $categoriesManifestation = [];
        foreach ($datas['hasTheme'] as $theme) {
            $categoriesManifestation[] = $this->getDataValue($theme, '[rdfs:label][fr][0]');
        }
        $categoriesManifestation = array_filter(array_unique($categoriesManifestation));

        $country = $this->getDataValue($datas, '[isLocatedAt][0][schema:address][0][hasAddressCity][0][isPartOfDepartment][0][isPartOfRegion][0][isPartOfCountry][0][rdfs:label][fr][0]');
        $latitude = (float) $this->getDataValue($datas, '[isLocatedAt][0][schema:geo][schema:latitude]');
        $longitude = (float) $this->getDataValue($datas, '[isLocatedAt][0][schema:geo][schema:longitude]');

        $emails = [];
        $phones = [];
        $websites = [];

        foreach (['hasBookingContact', 'hasContact'] as $key) {
            foreach ($datas[$key] as $currentDatas) {
                if (!empty($currentDatas['schema:email'])) {
                    $emails = [...$emails, ...(array) $currentDatas['schema:email']];
                }

                if (!empty($currentDatas['schema:telephone'])) {
                    $phones = [...$phones, ...(array) $currentDatas['schema:telephone']];
                }

                if (!empty($currentDatas['foaf:homepage'])) {
                    $websites = [...$websites, ...(array) $currentDatas['foaf:homepage']];
                }
            }
        }

        $websites = array_filter(array_unique($websites));
        $phones = array_filter(array_unique($phones));
        $emails = array_filter(array_unique($emails));

        $lastUpdate = new DateTimeImmutable($datas['lastUpdate']);
        $lastUpdate->setTime(0, 0);

        if (isset($datas['lastUpdateDatatourisme'])) {
            $lastUpdateDatatourisme = new DateTimeImmutable($datas['lastUpdateDatatourisme']);
        } else {
            $lastUpdateDatatourisme = null;
        }
        $updatedAt = max($lastUpdate, $lastUpdateDatatourisme);

        $description = $this->getDataValue($datas, [
            '[hasDescription][0][dc:description][fr][0]',
            '[rdfs:comment][fr][0]',
            '[rdfs:label][fr][0]',
        ]);

        $url = $this->getDataValue($datas, '[hasMainRepresentation][0][ebucore:hasRelatedResource][0][ebucore:locator][0]');

        $event = new EventDto();
        $event->externalUpdatedAt = $updatedAt;
        $event->name = $this->getDataValue($datas, '[rdfs:label][fr][0]');
        $event->description = $description;
        $event->type = implode(', ', $typesManifestation) ?: null;
        $event->category = implode(', ', $categoriesManifestation) ?: null;
        $event->source = $datas['@id'];
        $event->latitude = $latitude;
        $event->longitude = $longitude;
        $event->imageUrl = $url;
        $event->websiteContacts = $websites;
        $event->emailContacts = $emails;
        $event->phoneContacts = $phones;

        $place = new PlaceDto();
        $place->name = $this->getDataValue($datas, [
            '[isLocatedAt][0][schema:address][0][schema:addressLocality][0]',
            '[isLocatedAt][0][schema:address][0][schema:addressLocality]',
        ]);
        $place->street = $this->getDataValue($datas, '[isLocatedAt][0][schema:address][0][schema:streetAddress][0]');
        $place->postalCode = $this->getDataValue($datas, '[isLocatedAt][0][schema:address][0][schema:postalCode]');
        $place->externalId = sprintf('DT-%s', $this->getExternalIdFromUrl($this->getDataValue($datas, '[isLocatedAt][0][@id]')));

        $city = new CityDto();
        $city->name = $this->getDataValue($datas, [
            '[isLocatedAt][0][schema:address][0][schema:addressLocality][0]',
            '[isLocatedAt][0][schema:address][0][schema:addressLocality]',
        ]);

        $country = new CountryDto();
        $country->name = $country;

        //Multiple date handling
        foreach ($datas['takesPlaceAt'] as $date) {
            if (empty($date['endDate'])) {
                continue;
            }
            $startDate = new DateTimeImmutable($date['startDate']);
            $endDate = new DateTimeImmutable($date['endDate']);
            $hours = null;

            $startTime = $date['startTime'] ?? null;
            $endTime = $date['endTime'] ?? null;

            if ($startTime && $endTime) {
                $startTime = preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $startTime);
                $endTime = preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $endTime);
                $hours = sprintf('De %s à %s', $startTime, $endTime);
            } elseif ($startTime) {
                $startTime = preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $startTime);
                $hours = sprintf('À %s', $startTime);
            }

            $currentEvent = clone $event;
            $currentEvent->externalId = sprintf('DT-%s-%s', $datas['dc:identifier'], $this->getExternalIdFromUrl($date['@id']));
            $currentEvent->startDate = $startDate;
            $currentEvent->endDate = $endDate;
            $currentEvent->hours = $hours;

            $events[] = $currentEvent;
        }

        return $events;
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
            'schema:TheaterEvent' => 'Théâtre',
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

    /**
     * @param string|string[] $paths
     */
    private function getDataValue(array $datas, $paths, $defaultValue = null)
    {
        foreach ((array) $paths as $path) {
            try {
                return $this->propertyAccessor->getValue($datas, $path);
            } catch (AccessException | UnexpectedTypeException $e) {
            }
        }

        return $defaultValue;
    }

    private function getExternalIdFromUrl(string $url): string
    {
        $path = ltrim(parse_url($url, PHP_URL_PATH), '/');

        if (!preg_match(self::UUID_REGEX, $path)) {
            throw new RuntimeException(sprintf('Unable to guess id FROM url "%s"', $url));
        }

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'datatourisme';
    }
}
