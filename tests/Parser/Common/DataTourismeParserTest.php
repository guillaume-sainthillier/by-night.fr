<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Parser\Common;

use App\Dto\EventDto;
use App\Dto\TagDto;
use App\Handler\EventHandler;
use App\Import\EventPublicationGuard;
use App\Parser\Common\DataTourismeParser;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The parser reads the DATAtourisme API (flattened ontology, cursor pagination) through a
 * scoped client carrying the API key; the mock below is wrapped the way
 * config/packages/http_client.yaml wires the real "datatourisme.client".
 */
final class DataTourismeParserTest extends AppKernelTestCase
{
    private const string BASE_URI = 'https://api.datatourisme.fr/v1/';

    private const string SECOND_PAGE = 'https://api.datatourisme.fr/v1/entertainmentAndEvent?page_size=250&lang=fr&crs=i45WMjAwMDUyM0vRNbE0';

    /** @var list<array{method: string, url: string, apiKey: list<string>}> */
    private array $requests = [];

    /** @var list<MockResponse> served in order */
    private array $responses = [];

    /** @var list<EventDto> */
    private array $dispatched = [];

    private DataTourismeParser $parser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $mock = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'apiKey' => $options['normalized_headers']['x-api-key'] ?? []];
            $response = array_shift($this->responses);
            self::assertNotNull($response, \sprintf('Unexpected request %s %s', $method, $url));

            return $response;
        });
        $client = ScopingHttpClient::forBaseUri($mock, self::BASE_URI, ['headers' => ['X-API-Key' => 'test-key']]);

        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $message, array $stamps = []): Envelope {
            self::assertInstanceOf(EventDto::class, $message);
            $this->dispatched[] = $message;

            return new Envelope($message, $stamps);
        });

        $this->parser = new DataTourismeParser(new NullLogger(), $bus, self::getContainer()->get(EventHandler::class), $client);
        $this->parser->setPublicationGuard(self::getContainer()->get(EventPublicationGuard::class));
    }

    public function testFullImportPagesThroughTheUpcomingEvents(): void
    {
        $this->responses = [
            self::page([self::apiEvent(['identifier' => 'A']), self::apiEvent(['identifier' => 'B'])], self::SECOND_PAGE),
            self::page([self::apiEvent(['identifier' => 'C'])], null),
        ];

        $this->parser->parse(null);

        self::assertCount(2, $this->requests);
        [$first, $second] = $this->requests;
        self::assertSame('GET', $first['method']);
        self::assertStringStartsWith(self::BASE_URI . 'entertainmentAndEvent?', $first['url']);
        $query = self::query($first['url']);
        self::assertSame('250', $query['page_size'], 'The API maximum');
        self::assertSame('fr', $query['lang']);
        self::assertSame(\sprintf('takesPlaceAt.endDate[gte]=%s', date('Y-m-d')), $query['filters'], 'A full import takes every event still to come, whenever it changed');
        self::assertStringContainsString('takesPlaceAt', $query['fields']);
        self::assertSame(['X-API-Key: test-key'], $first['apiKey']);
        self::assertSame(self::SECOND_PAGE, $second['url'], 'The cursor link is followed as is');
        self::assertSame(['X-API-Key: test-key'], $second['apiKey']);
        self::assertSame(['A', 'B', 'C'], $this->externalIds());
        self::assertSame(3, $this->parser->getParsedEvents());
    }

    public function testIncrementalImportOnlyAsksForTheEventsUpdatedSinceThePreviousRun(): void
    {
        $this->responses = [self::page([], null)];

        // 00:30 UTC: the safety margin pushes the lower bound to the previous day
        $this->parser->parse(new DateTimeImmutable('2026-09-22 00:30:00', new DateTimeZone('UTC')));

        self::assertCount(1, $this->requests);
        self::assertSame(
            \sprintf('takesPlaceAt.endDate[gte]=%s and lastUpdateDatatourisme[gte]=2026-09-21', date('Y-m-d')),
            self::query($this->requests[0]['url'])['filters']
        );
        self::assertSame(0, $this->parser->getParsedEvents());
    }

    public function testAnApiObjectIsMappedToAnEvent(): void
    {
        $this->responses = [self::page([self::apiEvent()], null)];

        $this->parser->parse(null);

        self::assertCount(1, $this->dispatched);
        $event = $this->dispatched[0];
        self::assertSame('FMAFMAYSPTHEATREMUNICIPALPAZENAISYSPLAFOLLEREPARTENTHESE171026', $event->externalId, 'The producer identifier keeps the identity the flux gave');
        self::assertSame('datatourisme', $event->externalOrigin);
        self::assertSame('Data Tourisme', $event->fromData);
        self::assertSame('https://data.datatourisme.fr/49/a42cf45a-eda0-3164-92ee-e0feabb09822', $event->source);
        self::assertSame('La folle repart en thèse', $event->name);
        self::assertStringStartsWith('A propos du spectacle', (string) $event->description);
        self::assertSame('Musique, Culture, Concert', $event->type);
        self::assertSame('Humour', $event->category?->name);
        self::assertSame(['Spectacle'], array_map(static fn (TagDto $tag): ?string => $tag->name, $event->themes));
        self::assertSame('2026-09-19T00:25:50+00:00', $event->externalUpdatedAt?->format(DateTimeInterface::ATOM), 'The later of the producer and platform dates');
        self::assertSame('https://cdt64.media.tourinsoft.eu/upload/Site-de-Bergerac.jpg', $event->imageUrl);
        self::assertSame(['http://www.sainte-pazanne.fr/listes/theatre-municipal-pazenais'], $event->websiteContacts);
        self::assertSame(['+33 6 99 04 04 34', '+33 2 40 02 43 74'], $event->phoneContacts);
        self::assertNull($event->emailContacts, 'The API exposes no e-mail');
        self::assertSame('2026-10-17', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-10-17', $event->endDate?->format('Y-m-d'));
        self::assertSame('À 20h30', $event->hours, 'An end time equal to the start time is a placeholder');
        self::assertCount(1, $event->timesheets);
        self::assertSame(47.10078, $event->latitude);
        self::assertSame(-1.81272, $event->longitude);
        self::assertSame('TMP - Théâtre Municipal Pazenais', $event->place?->name, 'The venue is read from the address lines');
        self::assertEqualsIgnoringCase('7 rue du Ballon', (string) $event->place?->street);
        self::assertMatchesRegularExpression('/^DT-[0-9a-f]{32}$/', (string) $event->place?->externalId);
        self::assertSame('Sainte-Pazanne', $event->place?->city?->name);
        self::assertSame('44680', $event->place?->city?->postalCode);
        self::assertSame('France', $event->place?->country?->name);
        self::assertSame($event->place?->country, $event->place?->city?->country, 'City and place share one country DTO');
    }

    public function testTheSameAddressIsTheSameVenueWhateverTheEvent(): void
    {
        $this->responses = [self::page([
            self::apiEvent(['identifier' => 'A', 'label' => ['@fr' => 'Concert']]),
            self::apiEvent(['identifier' => 'B', 'label' => ['@fr' => 'Théâtre']]),
            self::apiEvent(['identifier' => 'C', 'isLocatedAt' => [self::location(['streetAddress' => ['Les Halles']])]]),
        ], null)];

        $this->parser->parse(null);

        $venues = array_map(static fn (EventDto $event): ?string => $event->place?->externalId, $this->dispatched);
        self::assertSame($venues[0], $venues[1]);
        self::assertNotSame($venues[0], $venues[2]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideUnusableObjects(): iterable
    {
        yield 'obsolete' => [['isObsolete' => true]];
        yield 'no schedule' => [['takesPlaceAt' => []]];
        yield 'schedule without dates' => [['takesPlaceAt' => [['startTime' => '20:00']]]];
        yield 'no location' => [['isLocatedAt' => []]];
        yield 'location without address' => [['isLocatedAt' => [['geo' => ['latitude' => 47.1, 'longitude' => -1.8]]]]];
    }

    #[DataProvider('provideUnusableObjects')]
    public function testAnObjectWithoutDatesOrAddressIsSkipped(array $overrides): void
    {
        $this->responses = [self::page([self::apiEvent($overrides)], null)];

        $this->parser->parse(null);

        self::assertSame([], $this->dispatched);
    }

    public function testDatatourismeUuidStandsInForAMissingProducerIdentifier(): void
    {
        $this->responses = [self::page([self::apiEvent(['identifier' => null])], null)];

        $this->parser->parse(null);

        self::assertSame(['0000eb03-9346-3cc8-ab23-dbb259b8e493'], $this->externalIds());
    }

    public function testDatatourismeUuidStandsInForAProducerIdentifierTooLongToStore(): void
    {
        $this->responses = [self::page([self::apiEvent(['identifier' => str_repeat('Salle-des-fetes-Concert-du-samedi-', 5)])], null)];

        $this->parser->parse(null);

        self::assertSame(['0000eb03-9346-3cc8-ab23-dbb259b8e493'], $this->externalIds());
    }

    public function testEveryPeriodOfTheScheduleBecomesATimesheet(): void
    {
        $this->responses = [self::page([self::apiEvent(['takesPlaceAt' => [
            ['startDate' => '2026-11-14', 'endDate' => '2026-11-14', 'startTime' => '09:00', 'endTime' => '12:30'],
            ['startDate' => '2026-11-14', 'endDate' => '2026-11-14', 'startTime' => '13:30', 'endTime' => '18:30'],
            ['startDate' => '2026-11-21', 'endDate' => '2026-11-22'],
        ]])], null)];

        $this->parser->parse(null);

        $event = $this->dispatched[0];
        self::assertSame('2026-11-14', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-11-22', $event->endDate?->format('Y-m-d'));
        self::assertSame(
            ['De 09h00 à 12h30', 'De 13h30 à 18h30', null],
            array_map(static fn ($timesheet): ?string => $timesheet->hours, $event->timesheets)
        );
        self::assertNull($event->hours, 'Several distinct schedules: no single summary');
    }

    public function testTheEventSpansItsPeriodsWhateverTheirOrder(): void
    {
        // As served for event 3491797: the latest period first
        $this->responses = [self::page([self::apiEvent(['takesPlaceAt' => [
            ['startDate' => '2026-09-11', 'endDate' => '2026-09-11'],
            ['startDate' => '2026-08-14', 'endDate' => '2026-08-14'],
            ['startDate' => '2026-08-20', 'endDate' => '2026-08-21'],
        ]])], null)];

        $this->parser->parse(null);

        $event = $this->dispatched[0];
        self::assertSame('2026-08-14', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-09-11', $event->endDate?->format('Y-m-d'));
    }

    public function testDescriptionFallsBackToShortDescriptionThenComment(): void
    {
        $this->responses = [self::page([
            self::apiEvent(['identifier' => 'short', 'hasDescription' => [['shortDescription' => ['@fr' => 'La courte.']]]]),
            self::apiEvent(['identifier' => 'comment', 'hasDescription' => []]),
            self::apiEvent(['identifier' => 'label', 'hasDescription' => [], 'comment' => null]),
        ], null)];

        $this->parser->parse(null);

        self::assertSame(
            ['short' => 'La courte.', 'comment' => 'Le Théâtre Municipal de Sainte-Pazanne vous accueille pour le lancement de sa saison culturelle !', 'label' => 'La folle repart en thèse'],
            array_column(array_map(static fn (EventDto $event): array => ['id' => $event->externalId, 'description' => $event->description], $this->dispatched), 'description', 'id')
        );
    }

    /**
     * @return iterable<string, array{list<string>, ?string, ?string}>
     */
    public static function provideAddressLines(): iterable
    {
        yield 'venue then street' => [['TMP - Théâtre Municipal Pazenais', '7 rue du Ballon'], 'TMP - Théâtre Municipal Pazenais', '7 rue du Ballon'];
        yield 'street then venue' => [['70 Boulevard Joseph Santraille', 'Alliance Aquitaine'], 'Alliance Aquitaine', '70 Boulevard Joseph Santraille'];
        yield 'venue alone' => [['Salle des fêtes'], 'Salle des fêtes', null];
        yield 'street alone' => [['6 rue Gorth'], null, '6 rue Gorth'];
        yield 'a square is a way' => [['Place Notre-Dame'], null, 'Place Notre-Dame'];
        yield 'abbreviated way' => [['Av. de la Gare', 'Cinéma Le Doron'], 'Cinéma Le Doron', 'Av. de la Gare'];
        yield 'road number' => [['RD 940', 'Aire des Pins'], 'Aire des Pins', 'RD 940'];
        yield 'town centre is no venue' => [['Centre Ville'], null, 'Centre Ville'];
        yield 'venue starting with a way word' => [['Village des sciences', 'Allée du Parc'], 'Village des sciences', 'Allée du Parc'];
        yield 'accented venue' => [['Château de Blois'], 'Château de Blois', null];
        yield 'line repeating the town' => [['Sainte-Pazanne'], null, null];
        yield 'two venue-like lines' => [['Ospedale', 'Cartalavonu'], 'Ospedale', 'Cartalavonu'];
        yield 'no line' => [[], null, null];
    }

    /**
     * @param list<string> $lines
     */
    #[DataProvider('provideAddressLines')]
    public function testTheVenueIsReadFromTheAddressLines(array $lines, ?string $name, ?string $street): void
    {
        self::assertSame(
            ['name' => $name, 'street' => $street],
            new ReflectionMethod(DataTourismeParser::class, 'venue')->invoke(null, $lines, 'Sainte-Pazanne')
        );
    }

    /**
     * @return list<string|null>
     */
    private function externalIds(): array
    {
        $ids = array_map(static fn (EventDto $dto): ?string => $dto->externalId, $this->dispatched);
        sort($ids);

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    private static function query(string $url): array
    {
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

        /* @var array<string, string> $query */
        return $query;
    }

    /**
     * A page of the paginated envelope; $next is the cursor link of the following page.
     *
     * @param list<array<string, mixed>> $objects
     */
    private static function page(array $objects, ?string $next): MockResponse
    {
        return new MockResponse(json_encode([
            'objects' => $objects,
            'meta' => ['total' => 3, 'page' => 1, 'page_size' => 250, 'total_pages' => 1, 'next' => $next, 'previous' => null],
        ], \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    /**
     * An event as the API returns it with our field selection (lang=fr), taken from a real
     * response of 2026-09-22.
     *
     * @param array<string, mixed> $overrides top-level fields to replace
     *
     * @return array<string, mixed>
     */
    private static function apiEvent(array $overrides = []): array
    {
        return array_replace([
            'uuid' => '0000eb03-9346-3cc8-ab23-dbb259b8e493',
            'uri' => 'https://data.datatourisme.fr/49/a42cf45a-eda0-3164-92ee-e0feabb09822',
            'identifier' => 'FMAFMAYSPTHEATREMUNICIPALPAZENAISYSPLAFOLLEREPARTENTHESE171026',
            'label' => ['@fr' => 'La folle repart en thèse'],
            'type' => ['PointOfInterest', 'MusicEvent', 'Event', 'CulturalEvent', 'EntertainmentAndEvent', 'Concert'],
            'lastUpdate' => '2026-09-18',
            'lastUpdateDatatourisme' => '2026-09-19T00:25:50.441Z',
            'hasDescription' => [['description' => ['@fr' => " A propos du spectacle : Un one-woman show atypique époustouflant, 1h30 de follie.\n\nDans un seule en scène, Liane Foly retrace son parcours."]]],
            'comment' => ['@fr' => 'Le Théâtre Municipal de Sainte-Pazanne vous accueille pour le lancement de sa saison culturelle !'],
            'hasTheme' => [['label' => ['@fr' => 'Humour'], 'key' => 'Humour'], ['label' => ['@fr' => 'Spectacle'], 'key' => 'Show']],
            'hasMainRepresentation' => [['hasRelatedResource' => [['hasMimeType' => [['label' => ['@fr' => 'image/jpeg']]], 'locator' => ['https://cdt64.media.tourinsoft.eu/upload/Site-de-Bergerac.jpg']]]]],
            'takesPlaceAt' => [['startDate' => '2026-10-17', 'endDate' => '2026-10-17', 'startTime' => '20:30', 'endTime' => '20:30']],
            'isLocatedAt' => [self::location()],
            'hasContact' => [['telephone' => ['+33 6 99 04 04 34', '+33 2 40 02 43 74'], 'homepage' => ['http://www.sainte-pazanne.fr/listes/theatre-municipal-pazenais']]],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $address fields of the postal address to replace
     *
     * @return array<string, mixed>
     */
    private static function location(array $address = []): array
    {
        return [
            'geo' => ['latitude' => 47.10078, 'longitude' => -1.81272],
            'address' => [array_replace([
                'addressLocality' => 'Sainte-Pazanne',
                'postalCode' => '44680',
                'streetAddress' => ['TMP - Théâtre Municipal Pazenais', '7 rue du Ballon'],
                'hasAddressCity' => [
                    'insee' => '44186',
                    'label' => ['@fr' => 'Sainte-Pazanne'],
                    'isPartOfDepartment' => [
                        'insee' => '44',
                        'label' => ['@fr' => 'Loire-Atlantique'],
                        'isPartOfRegion' => ['insee' => '52', 'label' => ['@fr' => 'Pays de la Loire'], 'isPartOfCountry' => ['label' => ['@fr' => 'France']]],
                    ],
                ],
            ], $address)],
            'geoPoint' => ['lon' => -1.81272, 'lat' => 47.10078],
        ];
    }
}
