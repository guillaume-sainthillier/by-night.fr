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
use App\Dto\RemovedEventDto;
use App\Dto\TagDto;
use App\Handler\EventHandler;
use App\Parser\AbstractParser;
use App\Utils\StartingPrice;
use DateTimeImmutable;
use DateTimeZone;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
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
     * Length of event.external_id and parser_data.external_id.
     */
    private const int MAX_EXTERNAL_ID_LENGTH = 127;

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
        'offers.textPriceSpecification',
        'offers.priceSpecification.name',
        'offers.priceSpecification.price',
        'offers.priceSpecification.minPrice',
        'offers.priceSpecification.maxPrice',
        'offers.priceSpecification.priceCurrency',
        'offers.priceSpecification.additionalInformation',
        'offers.priceSpecification.hasEligiblePolicy.key',
        'offers.priceSpecification.hasEligiblePolicy.label',
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
        #[Target('datatourisme.client')]
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
     * 4.1: the prices are read from the offers.
     */
    #[Override]
    public static function getParserVersion(): string
    {
        return '4.1';
    }

    /**
     * {@inheritDoc}
     */
    protected function fetchEvents(?DateTimeImmutable $since, bool $includePast): iterable
    {
        // Outside a backfill a past event is useless whatever changed: both imports keep only
        // the events still running or to come. The API compares dates at day granularity, so
        // an incremental import starts from the (UTC) day the previous run started: at most
        // one day of overlap, which the publication guard drops for free.
        $filters = [];
        if (!$includePast) {
            $filters[] = \sprintf('takesPlaceAt.endDate[gte]=%s', new DateTimeImmutable('today')->format('Y-m-d'));
        }
        if (null !== $since) {
            $filters[] = \sprintf('lastUpdateDatatourisme[gte]=%s', self::withSafetyMargin($since)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d'));
        }

        $url = 'entertainmentAndEvent';
        $query = [
            'fields' => implode(',', self::FIELDS),
            'lang' => 'fr',
            'page_size' => self::PAGE_SIZE,
        ];
        if ([] !== $filters) {
            $query['filters'] = implode(' and ', $filters);
        }

        // Page numbers stop working past 10 000 results: follow the cursor links instead.
        while (null !== $url) {
            $data = $this->datatourismeClient->request('GET', $url, ['query' => $query])->toArray();

            foreach ($data['objects'] ?? [] as $object) {
                // An object "no longer current" is a tombstone: the event imported from it is gone at the source
                if (true === ($object['isObsolete'] ?? false)) {
                    yield new RemovedEventDto($this->externalId($object));

                    continue;
                }

                yield $this->mapRecord(fn (): ?EventDto => $this->arrayToDto($object), ['uuid' => $object['uuid'] ?? null]);
            }

            $url = $data['meta']['next'] ?? null;
            $query = []; // the "next" link carries the whole query string
        }
    }

    /**
     * The producer's identifier is what the Diffuseur flux exposed as dc:identifier, so the
     * events imported before the API keep their identity; DATAtourisme's own uuid steps in
     * when a producer sends none, or one too long for event.external_id (some producers
     * compose it from the venue, the title and the date).
     *
     * @param array<string, mixed> $data
     */
    private function externalId(array $data): string
    {
        $identifier = $this->string($data['identifier'] ?? null);

        return null !== $identifier && mb_strlen($identifier) <= self::MAX_EXTERNAL_ID_LENGTH ? $identifier : (string) $data['uuid'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToDto(array $data): ?EventDto
    {
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

        // The booking contact's website is where to book, the others are the organiser's or the venue's
        $ticketUrl = $this->first($data['hasBookingContact'][0]['homepage'] ?? null);

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

        $event = new EventDto();
        $event->fromData = self::getParserName();
        $event->externalId = $this->externalId($data);
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
        $event->ticketUrl = $ticketUrl;
        $event->phoneContacts = array_values(array_unique(array_filter($phones)));
        $event->emailContacts = array_values(array_unique(array_filter($emails)));
        // The periods come in no particular order: the event spans from the earliest to the latest
        $event->startDate = min(array_map(static fn (EventTimesheetDto $timesheet) => $timesheet->startAt, $timesheets));
        $event->endDate = max(array_map(static fn (EventTimesheetDto $timesheet) => $timesheet->endAt, $timesheets));
        $event->prices = $this->prices((array) ($data['offers'] ?? []));
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
            $timesheet->startTime = $this->time($period['startTime'] ?? null);
            $timesheet->endTime = $this->time($period['endTime'] ?? null);
            $timesheets[] = $timesheet;
        }

        return $timesheets;
    }

    /**
     * "20:30" or "20:30:00": an end equal to the start is a placeholder many producers send, which the Cleaner drops.
     */
    private function time(mixed $time): ?DateTimeImmutable
    {
        $time = $this->string($time);

        return null === $time ? null : (DateTimeImmutable::createFromFormat('!H:i', substr($time, 0, 5)) ?: null);
    }

    /**
     * The prices as one text, as the other sources give theirs ("Tarif réduit : 10€ - 12€ - Tarif enfant : Gratuit").
     *
     * The amounts of the price specifications come first: the producer's own summary (textPriceSpecification) reads
     * "Plein tarif : de 10 à 28 €", whose 10 no amount pattern can tell from a number (StartingPrice). The summary
     * stands in when no specification has an amount ("Gratuit", "Payant"), then the specifications that say the
     * entry is free.
     *
     * @param list<array<string, mixed>> $offers
     */
    private function prices(array $offers): ?string
    {
        $summaries = [];
        $paying = [];
        $free = [];
        foreach ($offers as $offer) {
            $summary = $this->text($offer['textPriceSpecification'] ?? null);
            if (null !== $summary) {
                $summaries[] = trim((string) preg_replace('/\s+/u', ' ', $summary));
            }

            foreach ((array) ($offer['priceSpecification'] ?? []) as $specification) {
                [$line, $hasAmount] = $this->priceLine((array) $specification);
                if (null === $line) {
                    continue;
                }

                if ($hasAmount) {
                    $paying[] = $line;
                } else {
                    $free[] = $line;
                }
            }
        }

        return match (true) {
            [] !== $paying => implode(' - ', array_unique([...$paying, ...$free])),
            [] !== $summaries => implode(' - ', array_unique($summaries)),
            [] !== $free => implode(' - ', array_unique($free)),
            default => null,
        };
    }

    /**
     * A price specification as a line: its name or policy, and its amounts ("Tarif réduit : 10€", "De 5€ à 8€"), or
     * that it is free ("Gratuit", "Tarif enfant : Gratuit"). None for a specification that says neither.
     *
     * Its minPrice and maxPrice lists are not paired (minPrice [40, 10] with maxPrice [35, 40] is seen): every amount
     * counts.
     *
     * @param array<string, mixed> $specification
     *
     * @return array{?string, bool} the line, and whether it has an amount
     */
    private function priceLine(array $specification): array
    {
        $policies = (array) ($specification['hasEligiblePolicy'] ?? []);
        $label = $this->text($specification['name'] ?? null) ?? $this->text($policies[0]['label'] ?? null);

        $amounts = [];
        foreach (['price', 'minPrice', 'maxPrice'] as $key) {
            foreach ((array) ($specification[$key] ?? []) as $amount) {
                if (is_numeric($amount) && $amount >= 0) {
                    $amounts[] = (float) $amount;
                }
            }
        }

        // Only zeros say it is free, as a free policy does: "Gratuit", not "Gratuit : 0€"
        if ([] !== $amounts && max($amounts) > 0) {
            $currency = mb_strtoupper($this->string($specification['priceCurrency'] ?? null) ?? 'EUR');
            $unit = 'EUR' === $currency ? '€' : ' ' . $currency;
            $min = self::formatPrice(min($amounts)) . $unit;
            $max = self::formatPrice(max($amounts)) . $unit;
            $amount = $min === $max ? $min : \sprintf('De %s à %s', $min, $max);

            return [null !== $label ? \sprintf('%s : %s', $label, $amount) : $amount, true];
        }

        // Free in words ("Gratuit", "Entrée libre"), as its name or its note says, or by its policy or its zeros alone
        $words = $label ?? $this->text($specification['additionalInformation'] ?? null);
        if (null !== $words && 0.0 === StartingPrice::fromPrices($words)) {
            return [$words, false];
        }

        if ([] !== $amounts || array_any($policies, static fn (mixed $policy): bool => \is_array($policy) && 'Free' === ($policy['key'] ?? null))) {
            return [null !== $label ? \sprintf('%s : Gratuit', $label) : 'Gratuit', false];
        }

        return [null, false];
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
