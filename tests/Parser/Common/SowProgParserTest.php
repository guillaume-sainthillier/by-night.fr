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
use App\Enum\EventStatus;
use App\Handler\EventHandler;
use App\Parser\Common\SowProgParser;
use App\Tests\AppKernelTestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * One Sowprog API v2 event maps to one event with a timesheet per date.
 */
final class SowProgParserTest extends AppKernelTestCase
{
    public function testARecordMapsToAnEvent(): void
    {
        $event = $this->map(self::record());

        self::assertSame('Sow Prog', $event->fromData);
        self::assertSame('1234', $event->externalId);
        self::assertSame('2026-05-04 14:12', $event->externalUpdatedAt?->format('Y-m-d H:i'));
        self::assertSame('Trio Rosenberg', $event->name);
        self::assertSame('Concert', $event->type);
        self::assertSame('Jazz', $event->category?->name);
        self::assertSame(['Manouche'], array_map(static fn ($tag): ?string => $tag->name, $event->themes));
        self::assertSame('https://app.sowprog.com/uploads/original/img_event_xxx.jpg', $event->imageUrl, 'The relative image path is made absolute');
        self::assertSame(['https://tickets.example.com/rosenberg', 'https://www.example.com'], $event->websiteContacts, 'Ticketing first');
        self::assertSame('2026-06-15', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-06-15', $event->endDate?->format('Y-m-d'));
        self::assertNull($event->hours);
        self::assertSame(['21:00', '23:30'], [$event->timesheets[0]->startTime?->format('H:i'), $event->timesheets[0]->endTime?->format('H:i')]);
        self::assertSame('Plein tarif : 22€', $event->prices);
        self::assertNull($event->status);
        self::assertSame('7', $event->place?->externalId);
        self::assertSame('La Bellevilloise', $event->place->name);
        self::assertSame('19-21 Rue Boyer', $event->place->street);
        self::assertSame('75020', $event->place->city?->postalCode);
        self::assertSame('Paris', $event->place->city->name);
        self::assertSame('FR', $event->place->country?->code);
        self::assertEqualsWithDelta(48.8687, $event->latitude, 0.0001);
    }

    public function testPricesKeepTheirCents(): void
    {
        $event = $this->map(self::record(['dates' => [self::date('2026-06-15', '21:00:00', null, [
            ['label' => 'Tarif plein', 'price' => 12.5, 'currency' => 'EUR'],
            ['label' => 'Tarif réduit', 'price' => 8, 'currency' => 'EUR'],
        ])]]));

        self::assertSame('Tarif plein : 12.5€ - Tarif réduit : 8€', $event->prices);
    }

    public function testALabelEndingWithAColonIsNotFollowedByASecondOne(): void
    {
        // As served for a Paris Jazz Club concert: "Tarif concert à 21h : : 12€"
        $event = $this->map(self::record(['dates' => [self::date('2026-06-15', '21:00:00', null, [
            ['label' => 'Tarif concert à 21h :', 'price' => 12, 'currency' => 'EUR'],
        ])]]));

        self::assertSame('Tarif concert à 21h : 12€', $event->prices);
    }

    public function testAFreeAdmissionWithoutPricesIsFree(): void
    {
        $date = self::date('2026-06-15', '21:00:00', null, []);
        $date['freeAdmission'] = true;

        self::assertSame('Gratuit', $this->map(self::record(['dates' => [$date]]))->prices);
    }

    public function testEachDateIsATimesheet(): void
    {
        $event = $this->map(self::record(['dates' => [
            self::date('2026-10-01', '20:30:00', '23:00:00'),
            self::date('2026-10-02', '18:00:00', null),
            self::date('2026-10-03', null, null),
        ]]));

        self::assertSame(
            [['2026-10-01', '20:30', '23:00'], ['2026-10-02', '18:00', null], ['2026-10-03', null, null]],
            array_map(static fn ($timesheet): array => [$timesheet->startAt?->format('Y-m-d'), $timesheet->startTime?->format('H:i'), $timesheet->endTime?->format('H:i')], $event->timesheets),
        );
        self::assertNull($event->hours);
    }

    /**
     * DataTourisme took its range from the first and last periods as served, in no
     * particular order, and 11,485 events ended before they started (b6f45115).
     */
    public function testTheEventSpansItsDatesWhateverTheirOrder(): void
    {
        $event = $this->map(self::record(['dates' => [
            self::date('2026-11-20', '20:30:00', '23:00:00'),
            self::date('2026-10-01', '20:30:00', '23:00:00'),
            self::date('2026-10-15', '20:30:00', '23:00:00'),
        ]]));

        self::assertSame('2026-10-01', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-11-20', $event->endDate?->format('Y-m-d'));
    }

    public function testANightPastMidnightEndsTheNextDay(): void
    {
        $event = $this->map(self::record(['dates' => [self::date('2026-10-10', '23:00:00', '05:00:00')]]));

        self::assertSame(['23:00', '05:00'], [$event->timesheets[0]->startTime?->format('H:i'), $event->timesheets[0]->endTime?->format('H:i')]);
        self::assertSame('2026-10-10', $event->timesheets[0]->startAt?->format('Y-m-d'));
        self::assertSame('2026-10-11', $event->timesheets[0]->endAt?->format('Y-m-d'));
        self::assertSame('2026-10-11', $event->endDate?->format('Y-m-d'));
    }

    public function testTheTextsLoseTheirHtmlEntities(): void
    {
        // As served on 2026-10-05 ("&#58;" in 12 descriptions, "&amp;" in a tagline)
        $event = $this->map(self::record(['title' => 'Orgue &amp; Guitare', 'description' => 'Le trio &#58; &quot;Swing Folies&quot;']));

        self::assertSame('Orgue & Guitare', $event->name);
        self::assertStringContainsString('<p>Le trio : &quot;Swing Folies&quot;</p>', (string) $event->description);
    }

    public function testTheDescriptionLeadsWithTheTaglineAndKeepsItsParagraphs(): void
    {
        $event = $this->map(self::record([
            'tagline' => 'Cette soirée plaira aux fans de La Femme',
            'description' => "MARLON MAGNÉE (21h50)\r\n(Pop psych - Paris, FR)\r\n\r\nAprès quinze ans <avec> La Femme.",
            'artists' => [self::artist('Marlon Magnée', 1)],
        ]));

        self::assertSame(
            "<p><strong>Cette soirée plaira aux fans de La Femme</strong></p>\n<p>MARLON MAGNÉE (21h50)<br>\n(Pop psych - Paris, FR)</p>\n<p>Après quinze ans &lt;avec&gt; La Femme.</p>",
            $event->description,
            'The text is escaped, the musician it names is not listed again',
        );
    }

    public function testATaglineTheTextOpensWithIsNotRepeated(): void
    {
        $event = $this->map(self::record(['tagline' => 'Notre histoire, en deux parties.', 'description' => "Notre histoire, en deux parties. Bârbad part en voyage.\nLa suite."]));

        self::assertStringStartsWith('<p>Notre histoire', (string) $event->description);
    }

    public function testTheLineUpListsTheArtistsTheTextLeavesOut(): void
    {
        $event = $this->map(self::record([
            'title' => 'Blow Up Trio',
            'tagline' => '',
            'description' => 'Le guitariste Hugo Corbin et ses complices.',
            'artists' => [self::artist('Martin Cazals', 2, 'batterie'), self::artist('Hugo Corbin - guitare/compos', 1)],
        ]));

        self::assertSame("<p>Le guitariste Hugo Corbin et ses complices.</p>\n<p><strong>Line-up :</strong> Hugo Corbin - guitare/compos, Martin Cazals (batterie)</p>", $event->description);
    }

    public function testAnEventThatIsItsOnlyArtistHasNoLineUp(): void
    {
        $event = $this->map(self::record(['tagline' => '', 'description' => 'Un concert.', 'artists' => [self::artist('Trio Rosenberg', 1)]]));

        self::assertSame('<p>Un concert.</p>', $event->description);
    }

    public function testACancelledEventKeepsItsReason(): void
    {
        $event = $this->map(self::record(['flag' => 'CANCELLED', 'cancelReason' => 'Artiste malade']));

        self::assertSame(EventStatus::Cancelled, $event->status);
        self::assertSame('Artiste malade', $event->statusMessage);
    }

    public function testARecordWithoutDateOrVenueIsLeftOut(): void
    {
        self::assertNull($this->invoke(self::record(['dates' => []])));
        self::assertNull($this->invoke(self::record(['venue' => null])));
    }

    public function testEveryPageIsRead(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): JsonMockResponse {
            $requests[] = $url;
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);

            return new JsonMockResponse([
                'data' => [self::record(['id' => (int) $query['page']])],
                'meta' => ['total' => 2, 'page' => (int) $query['page'], 'pageSize' => 1, 'totalPages' => 2],
            ]);
        }, 'https://app.sowprog.com/api/v2/');

        $events = iterator_to_array(new ReflectionMethod(SowProgParser::class, 'fetchEvents')->invoke($this->parser($client), null), false);

        self::assertSame(['1', '2'], array_map(static fn (?EventDto $event): ?string => $event?->externalId, $events));
        self::assertCount(2, $requests);
        self::assertStringContainsString('modifiedSince=0', $requests[0], 'A full import asks for everything');
    }

    private function parser(?MockHttpClient $client = null): SowProgParser
    {
        return new SowProgParser(
            new NullLogger(),
            self::getContainer()->get(MessageBusInterface::class),
            self::getContainer()->get(EventHandler::class),
            $client ?? new MockHttpClient(),
        );
    }

    private function map(array $record): EventDto
    {
        $event = $this->invoke($record);
        self::assertInstanceOf(EventDto::class, $event);

        return $event;
    }

    private function invoke(array $record): ?EventDto
    {
        /** @var EventDto|null $event */
        $event = new ReflectionMethod(SowProgParser::class, 'arrayToDto')->invoke($this->parser(), $record);

        return $event;
    }

    /**
     * @param list<array<string, mixed>>|null $prices
     *
     * @return array<string, mixed>
     */
    private static function date(string $date, ?string $startTime, ?string $endTime, ?array $prices = null): array
    {
        // As the API serves them, as datetimes
        return [
            'date' => $date . 'T00:00:00.000Z',
            'startTime' => null === $startTime ? null : '1970-01-01T' . $startTime . '.000Z',
            'endTime' => null === $endTime ? null : '1970-01-01T' . $endTime . '.000Z',
            'freeAdmission' => false,
            'prices' => $prices ?? [['label' => 'Plein tarif', 'price' => 22, 'currency' => 'EUR']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function artist(string $name, int $position, ?string $instrument = null): array
    {
        return ['id' => $position, 'name' => $name, 'instrument' => $instrument, 'position' => $position, 'musicBrainzId' => null, 'imageUrl' => null];
    }

    /**
     * As the API documentation shows a record, with the fields it leaves out of its example.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function record(array $overrides = []): array
    {
        return array_replace([
            'id' => 1234,
            'title' => 'Trio Rosenberg',
            'tagline' => 'Manouche jazz à La Bellevilloise',
            'description' => 'Le trio de jazz manouche.',
            'imageUrl' => '/uploads/original/img_event_xxx.jpg',
            'status' => 'PUBLISHED',
            'flag' => null,
            'cancelReason' => null,
            'category' => ['id' => 1, 'name' => 'Concert'],
            'styles' => [['id' => 12, 'name' => 'Jazz'], ['id' => 25, 'name' => 'Manouche']],
            'venue' => [
                'id' => 7,
                'name' => 'La Bellevilloise',
                'address' => '19-21 Rue Boyer',
                'city' => 'Paris',
                'postalCode' => '75020',
                'country' => 'FR',
                'latitude' => 48.8687,
                'longitude' => 2.3923,
                'capacity' => 600,
            ],
            'artists' => [
                ['id' => 89, 'name' => 'Trio Rosenberg', 'instrument' => null, 'position' => 1, 'musicBrainzId' => null, 'imageUrl' => null],
            ],
            'dates' => [self::date('2026-06-15', '21:00:00', '23:30:00')],
            'links' => [
                ['label' => 'Site', 'url' => 'https://www.example.com', 'type' => 'GENERAL'],
                ['label' => 'Billetterie', 'url' => 'https://tickets.example.com/rosenberg', 'type' => 'TICKETING'],
            ],
            'createdAt' => '2026-05-01T10:23:00.000Z',
            'updatedAt' => '2026-05-04T14:12:00.000Z',
        ], $overrides);
    }
}
