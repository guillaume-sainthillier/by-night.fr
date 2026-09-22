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
use App\Dto\EventTimesheetDto;
use App\Dto\PlaceDto;
use App\Dto\TagDto;
use App\Handler\EventHandler;
use App\Parser\AbstractParser;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports the "Fêtes et manifestations" of the DATAtourisme API (https://api.datatourisme.fr/v1/docs).
 *
 * The API replaces the Diffuseur flux (zip archives of JSON-LD files, shut down in October
 * 2027). Its objects are the same ontology flattened: plain keys ("label", not "rdfs:label"),
 * multilingual values as {"@fr": "…"} maps, and neither a venue identifier nor contact
 * e-mails any more. Requests go through the "datatourisme.client" scoped client, which
 * enforces the API quotas (see config/packages/rate_limiter.yaml).
 */
final class DataTourismeParser extends AbstractParser
{
    /**
     * The API maximum.
     */
    private const int PAGE_SIZE = 250;

    /**
     * Only what arrayToDto() reads, down to the sub-field (the selection is hierarchical, so
     * naming a parent would drag along its whole subtree: venue opening hours, day-of-week
     * labels, contact addresses…). Saves about a sixth of every page.
     */
    private const array FIELDS = [
        'uuid',
        'uri',
        'identifier',
        'label',
        'type',
        'isObsolete',
        'lastUpdate',
        'lastUpdateDatatourisme',
        'comment',
        'hasDescription.description',
        'hasDescription.shortDescription',
        'hasTheme.label',
        'hasMainRepresentation.hasRelatedResource.locator',
        'takesPlaceAt.startDate',
        'takesPlaceAt.endDate',
        'takesPlaceAt.startTime',
        'takesPlaceAt.endTime',
        'isLocatedAt.geo',
        'isLocatedAt.address',
        'hasContact.telephone',
        'hasContact.homepage',
        'hasBookingContact.telephone',
        'hasBookingContact.homepage',
    ];

    /**
     * An address line that names a way or an area rather than a venue: it starts with a
     * number, a way word ("rue", "av.", "allée"…) or a road number, or is just the town
     * centre. (*UCP) makes the word boundary Unicode-aware, so "Château" is not "ch.".
     */
    private const string STREET_LINE_REGEX = '/(*UCP)^(?:\d|(?:rue|ruelle|avenue|av|boulevard|bd|place|pl|route|rte|chemin|ch|all[ée]es?|impasse|quai|cours|passage|square|promenade|esplanade|lieu[- ]dit|hameau|voie|sentier|traverse|montée|faubourg|fbg|rond[- ]point|carrefour|parvis|venelle)(?:\.|\b)|(?:z\.?a\.?c?\.?|z\.?i\.?)(?=\s|$)|(?:rd|rn|d|n)\s?\d+\b|(?:le |la |les )?(?:bourg|village|centre[ -]?ville|centre[ -]?bourg)$)/iu';

    public function __construct(
        LoggerInterface $logger,
        MessageBusInterface $messageBus,
        EventHandler $eventHandler,
        private readonly HttpClientInterface $datatourismeClient,
    ) {
        parent::__construct($logger, $messageBus, $eventHandler);
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
     *
     * 4.0: the API replaced the Diffuseur flux, so every event is re-read once.
     */
    #[Override]
    public static function getParserVersion(): string
    {
        return '4.0';
    }

    /**
     * {@inheritDoc}
     */
    public function parse(?DateTimeImmutable $since): void
    {
        // A past event is useless whatever changed: both imports keep only the events still
        // running or to come. The API compares dates at day granularity, so an incremental
        // import starts from the (UTC) day the previous run started: at most one day of
        // overlap, which the publication guard drops for free.
        $filters = [\sprintf('takesPlaceAt.endDate[gte]=%s', new DateTimeImmutable('today')->format('Y-m-d'))];
        if (null !== $since) {
            $filters[] = \sprintf('lastUpdateDatatourisme[gte]=%s', self::withSafetyMargin($since)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d'));
        }

        $url = 'entertainmentAndEvent';
        $query = [
            'fields' => implode(',', self::FIELDS),
            'lang' => 'fr',
            'page_size' => self::PAGE_SIZE,
            'filters' => implode(' and ', $filters),
        ];

        // Page numbers stop working past 10 000 results: follow the cursor links instead.
        while (null !== $url) {
            $data = $this->datatourismeClient->request('GET', $url, ['query' => $query])->toArray();

            foreach ($data['objects'] ?? [] as $object) {
                $dto = $this->arrayToDto($object);
                if (null !== $dto) {
                    $this->publish($dto);
                }
            }

            $url = $data['meta']['next'] ?? null;
            $query = []; // the "next" link carries the whole query string
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToDto(array $data): ?EventDto
    {
        if (true === ($data['isObsolete'] ?? false)) {
            return null;
        }

        $location = $data['isLocatedAt'][0] ?? null;
        $address = $location['address'][0] ?? null;
        if (!\is_array($location) || !\is_array($address) || empty($data['takesPlaceAt'])) {
            return null;
        }

        $timesheets = $this->timesheets($data['takesPlaceAt']);
        if ([] === $timesheets) {
            return null;
        }

        $types = array_values(array_unique(array_filter(array_map($this->getFrenchType(...), (array) ($data['type'] ?? [])))));
        $themes = array_values(array_unique(array_filter(array_map(
            fn (array $theme): ?string => $this->text($theme['label'] ?? null),
            (array) ($data['hasTheme'] ?? []),
        ))));

        $websites = [];
        $phones = [];
        $emails = [];
        foreach ([...(array) ($data['hasBookingContact'] ?? []), ...(array) ($data['hasContact'] ?? [])] as $contact) {
            $websites = [...$websites, ...(array) ($contact['homepage'] ?? [])];
            $phones = [...$phones, ...(array) ($contact['telephone'] ?? [])];
            $emails = [...$emails, ...(array) ($contact['email'] ?? [])];
        }

        $lastUpdate = null === $this->string($data['lastUpdate'] ?? null) ? null : new DateTimeImmutable($data['lastUpdate'])->setTime(0, 0);
        $lastUpdateDatatourisme = null === $this->string($data['lastUpdateDatatourisme'] ?? null) ? null : new DateTimeImmutable($data['lastUpdateDatatourisme']);

        $hours = array_values(array_unique(array_filter(array_map(static fn (EventTimesheetDto $timesheet): ?string => $timesheet->hours, $timesheets))));

        $event = new EventDto();
        $event->fromData = self::getParserName();
        // The producer's identifier is what the Diffuseur flux exposed as dc:identifier, so
        // the events imported before the API keep their identity; DATAtourisme's own uuid
        // only steps in when a producer sends none.
        $event->externalId = $this->string($data['identifier'] ?? null) ?? $data['uuid'];
        $event->externalUpdatedAt = max($lastUpdate, $lastUpdateDatatourisme);
        $event->name = $this->text($data['label'] ?? null);
        $event->description = $this->text($data['hasDescription'][0]['description'] ?? null)
            ?? $this->text($data['hasDescription'][0]['shortDescription'] ?? null)
            ?? $this->text($data['comment'] ?? null)
            ?? $event->name;
        $event->type = implode(', ', $types);

        // First theme becomes the main category, the rest become themes
        if ([] !== $themes) {
            $event->category = TagDto::fromString(array_shift($themes));
            foreach ($themes as $theme) {
                $event->themes[] = TagDto::fromString($theme);
            }
        }

        $event->source = $this->string($data['uri'] ?? null);
        $event->latitude = (float) ($location['geo']['latitude'] ?? 0);
        $event->longitude = (float) ($location['geo']['longitude'] ?? 0);
        $event->imageUrl = $this->first($data['hasMainRepresentation'][0]['hasRelatedResource'][0]['locator'] ?? null);
        $event->websiteContacts = array_values(array_unique(array_filter($websites)));
        $event->phoneContacts = array_values(array_unique(array_filter($phones)));
        $event->emailContacts = array_values(array_unique(array_filter($emails)));
        $event->startDate = $timesheets[0]->startAt;
        $event->endDate = $timesheets[array_key_last($timesheets)]->endAt;
        $event->hours = 1 === \count($hours) ? $hours[0] : null;
        $event->timesheets = $timesheets;

        $streetLines = array_values(array_filter(array_map($this->string(...), (array) ($address['streetAddress'] ?? []))));
        $cityName = $this->string($address['addressLocality'] ?? null) ?? $this->text($address['hasAddressCity']['label'] ?? null);
        $postalCode = $this->string($address['postalCode'] ?? null);

        ['name' => $venueName, 'street' => $street] = self::venue($streetLines, $cityName);

        // The flux identified an address with a uuid the API dropped, so the normalised
        // address itself is now the venue identity: same lines at the same postal code,
        // same venue, whatever name the heuristic derives from them.
        $place = new PlaceDto();
        $place->name = $venueName ?? $cityName;
        $place->street = $street;
        $place->externalId = \sprintf('DT-%s', md5(mb_strtolower(preg_replace('/\s+/', ' ', implode('|', [...$streetLines, $postalCode ?? '', $cityName ?? ''])))));
        $event->place = $place;

        $city = new CityDto();
        $city->name = $cityName;
        $city->postalCode = $postalCode;
        $place->city = $city;

        $country = new CountryDto();
        $country->name = $this->text($address['hasAddressCity']['isPartOfDepartment']['isPartOfRegion']['isPartOfCountry']['label'] ?? null);
        $city->country = $country;
        $place->country = $country;

        return $event;
    }

    /**
     * One timesheet per period of the schedule; periods without dates are dropped.
     *
     * @param list<array<string, mixed>> $periods
     *
     * @return list<EventTimesheetDto>
     */
    private function timesheets(array $periods): array
    {
        $timesheets = [];
        foreach ($periods as $period) {
            $startDate = $this->string($period['startDate'] ?? null);
            $endDate = $this->string($period['endDate'] ?? null);
            if (null === $startDate || null === $endDate) {
                continue;
            }

            $timesheet = new EventTimesheetDto();
            $timesheet->startAt = new DateTimeImmutable($startDate);
            $timesheet->endAt = new DateTimeImmutable($endDate);
            $timesheet->hours = $this->hours($this->string($period['startTime'] ?? null), $this->string($period['endTime'] ?? null));
            $timesheets[] = $timesheet;
        }

        return $timesheets;
    }

    /**
     * "20:30" or "20:30:00" → "De 20h30 à 22h00" / "À 20h30" (also when the end equals the start,
     * a placeholder many producers send).
     */
    private function hours(?string $startTime, ?string $endTime): ?string
    {
        $startTime = null === $startTime ? null : preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $startTime);
        $endTime = null === $endTime ? null : preg_replace('#^(\d{2}):(\d{2}).*$#', '$1h$2', $endTime);

        if (null === $startTime) {
            return null;
        }

        if (null === $endTime || $endTime === $startTime) {
            return \sprintf('À %s', $startTime);
        }

        return \sprintf('De %s à %s', $startTime, $endTime);
    }

    /**
     * DATAtourisme describes where an event happens as a postal address, never as a venue,
     * yet producers mostly write the venue on an address line: "TMP - Théâtre Municipal
     * Pazenais" then "7 rue du Ballon", or "Salle des fêtes" alone. A line that names a way
     * is the street, a line repeating the town is noise, anything else is the venue. With
     * no venue line the caller falls back to the town, as the flux-era import always did.
     *
     * @param list<string> $lines
     *
     * @return array{name: ?string, street: ?string}
     */
    private static function venue(array $lines, ?string $town): array
    {
        $venues = [];
        $streets = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ('' === $line || (null !== $town && mb_strtolower($line) === mb_strtolower(trim($town)))) {
                continue;
            }

            if (1 === preg_match(self::STREET_LINE_REGEX, $line)) {
                $streets[] = $line;
            } else {
                $venues[] = $line;
            }
        }

        return [
            'name' => $venues[0] ?? null,
            'street' => $streets[0] ?? $venues[1] ?? null,
        ];
    }

    private function getFrenchType(string $type): ?string
    {
        $mapping = [
            'BusinessEvent' => 'Business',
            'ChildrensEvent' => 'Famille',
            'ComedyEvent' => 'Spectacle',
            'ShowEvent' => 'Spectacle',
            'CourseInstance' => 'Cours',
            'DanceEvent' => 'Danse',
            'EducationEvent' => 'Famille',
            'Exhibition' => 'Exposition',
            'ExhibitionEvent' => 'Exposition',
            'Festival' => 'Concert, Musique',
            'FoodEvent' => 'Nourriture',
            'LiteraryEvent' => 'Littérature',
            'MusicEvent' => 'Musique',
            'Concert' => 'Concert',
            'PublicationEvent' => 'Recherche',
            'BroadcastEvent' => 'Radio',
            'SaleEvent' => 'Commerce',
            'GarageSale' => 'Brocante',
            'SocialEvent' => 'Communautaire',
            'SportsEvent' => 'Sport',
            'SportsCompetition' => 'Compétition',
            'TheaterEvent' => 'Théâtre',
            'Theater' => 'Théâtre',
            'VisualArtsEvent' => 'Art',
            'CulturalEvent' => 'Culture',
        ];

        // The flux prefixed the schema.org classes ("schema:MusicEvent"), the API does not.
        return $mapping[preg_replace('/^schema:/', '', $type)] ?? null;
    }

    /**
     * Multilingual values come as {"@fr": "…"} maps (we ask for lang=fr); other texts are plain.
     */
    private function text(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = $value['@fr'] ?? (array_values($value)[0] ?? null);
        }

        return $this->first($value);
    }

    /**
     * A value the API serialises either as a string or as a list of strings.
     */
    private function first(mixed $value): ?string
    {
        if (\is_array($value)) {
            $value = $value[0] ?? null;
        }

        return $this->string($value);
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) && '' !== trim($value) ? $value : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getCommandName(): string
    {
        return 'datatourisme';
    }
}
