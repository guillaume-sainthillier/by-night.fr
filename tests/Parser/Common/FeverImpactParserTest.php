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
use App\Dto\EventTimesheetDto;
use App\Parser\Common\FeverImpactParser;
use App\Utils\HoursLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * Plans shaped as Fever's catalogue on Impact served them on 2026-10-28 ("2024 Impact Catalog"): its own columns, and
 * the items of the Partner API that file them under Impact's product fields.
 */
final class FeverImpactParserTest extends TestCase
{
    /**
     * A Candlelight in Aix-en-Provence, two evenings of two sessions.
     */
    private const array ROW = [
        'Country' => 'France',
        'State' => '-',
        'City' => 'Aix-en-Provence',
        'Venue' => '6MIC',
        'Location' => '160 Rue Pascal Duverger, Aix-en-Provence// 13090',
        'Coordinates' => '(43.5192132; 5.4224053)',
        'SKU' => '412345',
        'Plan Name' => 'Candlelight : Hommage à Coldplay',
        'Affiliate URL' => 'https://fever.pxf.io/c/8024471/1928078/16345?u=https%3A%2F%2Ffeverup.com%2Fm%2F412345',
        'Fever URL' => 'https://feverup.com/m/412345',
        'Description' => "⭐ Les concerts Candlelight à la lueur des bougies.\nPlacement par zone.",
        'Plan Rating' => '4.81',
        'Start date' => '2026-12-19T00:00:00+0000',
        'End date' => '2027-02-13T00:00:00+0000',
        'Sessions' => '2027-02-13 20:00, 2026-12-19 18:00, 2026-12-19 20:00, 2027-02-13 18:00',
        'Remaining Days' => '52.00',
        'Minimum Price' => '29.71',
        'Currency' => 'EUR',
        'Ticket prices' => '26.41 EUR, 29.71 EUR',
        'Availability of tickets' => 'high',
        'Category' => 'Tier 2: Candlelight Concerts',
        'Subcategory' => "Mother's Day, Concerts, Candlelight Concerts, Music Events",
        'Additional Notes' => '',
        'Photo 1' => 'https://applications-media.feverup.com/image/upload/f_auto,w_550,h_550/fever2/plan/photo/5c755a1c.jpg',
        'Photo 2' => '',
        'Country2' => 'France',
    ];

    /**
     * The same Candlelight as the Partner API serves it.
     */
    private const array ITEM = [
        'Id' => 'product_15532_412345',
        'CatalogItemId' => '412345',
        'Name' => 'Candlelight : Hommage à Coldplay',
        'Description' => "⭐ Les concerts Candlelight à la lueur des bougies.\nPlacement par zone.",
        'Bullets' => ['high'],
        'Labels' => ['26.41 EUR', ' 29.71 EUR'],
        'Manufacturer' => '2027-02-13 20:00, 2026-12-19 18:00, 2026-12-19 20:00, 2027-02-13 18:00',
        'Url' => 'https://fever.pxf.io/c/8024471/1928078/16345?u=https%3A%2F%2Ffeverup.com%2Fm%2F412345',
        'ImageUrl' => 'https://applications-media.feverup.com/image/upload/f_auto,w_550,h_550/fever2/plan/photo/5c755a1c.jpg',
        'AdditionalImageUrls' => [],
        'CurrentPrice' => '29.71',
        'Currency' => 'EUR',
        'ShippingLabel' => '160 Rue Pascal Duverger, Aix-en-Provence// 13090',
        'Category' => 'Tier 2: Candlelight Concerts',
        'SubCategory' => "Mother's Day, Concerts, Candlelight Concerts, Music Events",
        'Colors' => ['https://feverup.com/m/412345'],
        'Material' => '6MIC',
        'Pattern' => '(43.5192132; 5.4224053)',
        'Size' => 'France',
        'Text1' => 'France',
        'Text2' => 'Aix-en-Provence',
        'Text3' => '',
    ];

    public function testAnItemOfTheApiReadsAsTheCatalogueRow(): void
    {
        $columns = new ReflectionClass(FeverImpactParser::class)->getMethod('columns')->invoke(null, self::ITEM);

        self::assertEquals($this->parse(self::ROW), $this->parse($columns));
    }

    /**
     * Each country is asked for apart, and its pages followed to the last.
     */
    public function testEveryCountryIsReadPageAfterPage(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): JsonMockResponse {
            $requests[] = urldecode($url);
            if (str_contains($url, 'AfterId=')) {
                return new JsonMockResponse(['Items' => [[...self::ITEM, 'CatalogItemId' => '2']], '@nextpageuri' => '']);
            }

            if (str_contains(urldecode($url), "Text1 = 'France'")) {
                return new JsonMockResponse([
                    'Items' => [[...self::ITEM, 'CatalogItemId' => '1']],
                    '@nextpageuri' => '/Mediapartners/SID/Catalogs/15532/Items?PageSize=1000&Query=Text1+%3D+%27France%27&AfterId=MQ==',
                ]);
            }

            return new JsonMockResponse(['Items' => [], '@nextpageuri' => '']);
        }, 'https://api.impact.com/');

        $ref = new ReflectionClass(FeverImpactParser::class);
        $parser = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('impactClient')->setValue($parser, $client);
        $ref->getProperty('accountSid')->setValue($parser, 'SID');

        $events = iterator_to_array($ref->getMethod('fetchEvents')->invoke($parser, null, false), false);

        self::assertSame(['1', '2'], array_map(static fn (?EventDto $event): ?string => $event?->externalId, $events));
        self::assertCount(5, $requests, 'France on two pages, then Belgium, Switzerland and Monaco');
        self::assertStringStartsWith('https://api.impact.com/Mediapartners/SID/Catalogs/15532/Items?', $requests[0]);
        self::assertStringContainsString("Query=Text1 = 'France'", $requests[0]);
        self::assertStringContainsString("Query=Text1 = 'Monaco'", $requests[4]);
    }

    public function testACandlelightBecomesAnEvent(): void
    {
        $event = $this->parse(self::ROW);

        self::assertNotNull($event);
        self::assertSame('412345', $event->externalId);
        self::assertSame('Candlelight : Hommage à Coldplay', $event->name);
        self::assertSame('Concert', $event->type);
        self::assertSame(self::ROW['Affiliate URL'], $event->source);
        self::assertSame('https://applications-media.feverup.com/image/upload/fever2/plan/photo/5c755a1c.jpg', $event->imageUrl, 'The picture as uploaded, not the 550px square');
        self::assertSame('De 26.41€ à 29.71€', $event->prices, 'The tickets\' prices, not "Minimum Price"');
        self::assertSame('2026-12-19', $event->startDate?->format('Y-m-d'), 'The sessions come in no order');
        self::assertSame('2027-02-13', $event->endDate?->format('Y-m-d'));
        self::assertSame('6MIC', $event->place?->name);
        self::assertSame('160 Rue Pascal Duverger', $event->place->street);
        self::assertSame('Aix-en-Provence', $event->place->city?->name);
        self::assertSame('13090', $event->place->city->postalCode);
        self::assertSame('FR', $event->place->country?->code);
        self::assertSame(43.5192132, $event->place->latitude);
        self::assertSame(5.4224053, $event->place->longitude);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function leftOut(): iterable
    {
        yield 'another country' => [['Country' => 'Germany']];
        yield 'a gift card' => [['Plan Name' => 'Carte-cadeau Candlelight - Aix-en-Provence', 'Subcategory' => 'Gift Cards, For every occasion']];
        yield 'a voucher without a venue' => [['Venue' => 'Localisation Secrète Angers', 'Subcategory' => 'Cinema']];
        yield 'a plan without commission' => [['Category' => 'Tier 4: Non-commissionable Plans']];
        yield 'a plan of restricted commission' => [['Category' => 'Tier 5: Restricted Commission Plans']];
        yield 'a plan without sessions' => [['Sessions' => '']];
    }

    /**
     * @param array<string, string> $change
     */
    #[DataProvider('leftOut')]
    public function testWhatIsNoEventIsLeftOut(array $change): void
    {
        self::assertNull($this->parse([...self::ROW, ...$change]));
    }

    /**
     * @return iterable<string, array{string, string, ?string, ?string, ?string}>
     */
    public static function addresses(): iterable
    {
        yield 'street and town' => ['160 Rue Pascal Duverger, Aix-en-Provence// 13090', 'Aix-en-Provence', '160 Rue Pascal Duverger', 'Aix-en-Provence', '13090'];
        yield 'a venue sold in another market' => ['88 Rue Paul Bert, Lyon// 69003', 'Annecy', '88 Rue Paul Bert', 'Lyon', '69003'];
        yield 'the town in the local language' => ['Franklin Rooseveltplaats 11, Antwerpen// 2060', 'Antwerp', 'Franklin Rooseveltplaats 11', 'Antwerpen', '2060'];
        yield 'the postal code and the country written again' => ['1 Rue Porte Reine, 73000 Chambéry, France// 73000', 'Annecy', '1 Rue Porte Reine', 'Chambéry', '73000'];
        yield 'a street alone' => ['62 rue Lucien Faure// 33300', 'Bordeaux', '62 rue Lucien Faure', 'Bordeaux', '33300'];
        yield 'a street alone, without a number' => ['Rue du Cardinal Richaud// 33300', 'Bordeaux', 'Rue du Cardinal Richaud', 'Bordeaux', '33300'];
        yield 'a Swiss street alone' => ['Waldenburgerstrasse 34// 4052', 'Basel', 'Waldenburgerstrasse 34', 'Basel', '4052'];
        yield 'a town alone' => ['Saint-Émilion// 33330', 'Bordeaux', null, 'Saint-Émilion', '33330'];
        yield 'no postal code' => ['Bruxelles// ', 'Brussels', null, 'Bruxelles', null];
        yield 'a blank postal code' => ['Place Charles Rogier 20// 0000', 'Brussels', 'Place Charles Rogier 20', 'Brussels', null];
    }

    #[DataProvider('addresses')]
    public function testTheTownIsTheAddressOnesNotTheMarket(string $location, string $market, ?string $street, ?string $town, ?string $postalCode): void
    {
        $event = $this->parse([...self::ROW, 'Location' => $location, 'City' => $market]);

        self::assertNotNull($event);
        self::assertSame($street, $event->place?->street);
        self::assertSame($town, $event->place?->city?->name);
        self::assertSame($postalCode, $event->place?->city?->postalCode);
    }

    public function testEachSessionOfAnEveningIsListed(): void
    {
        $event = $this->parse(self::ROW);

        self::assertSame(
            ['2026-12-19 18:00', '2026-12-19 20:00', '2027-02-13 18:00', '2027-02-13 20:00'],
            array_map(static fn (EventTimesheetDto $timesheet): string => \sprintf('%s %s', $timesheet->startAt?->format('Y-m-d'), $timesheet->startTime?->format('H:i')), $event->timesheets ?? []),
        );
        self::assertSame([null, null, null, null], array_map(static fn (EventTimesheetDto $timesheet): ?string => $timesheet->hours, $event->timesheets ?? []));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function entries(): iterable
    {
        yield 'every quarter of an hour' => [['10:00', '10:15', '10:30', '10:45'], "puis toutes les 15 min jusqu'à 10h45"];
        yield 'every hour' => [['14:00', '15:00', '16:00', '17:00', '18:00'], "puis toutes les heures jusqu'à 18h"];
        yield 'every two hours' => [['10:00', '12:00', '14:00', '16:00'], "puis toutes les 2 heures jusqu'à 16h"];
        yield 'at no steady pace' => [['10:00', '10:30', '11:30', '18:45'], "puis 3 autres séances jusqu'à 18h45"];
    }

    /**
     * An exhibition lets visitors in every quarter of an hour: one session a day, at its first entry, its label going on
     * from it ("À 10h, puis toutes les 15 min jusqu'à 10h45"). The last entry is no end: the visit goes on past it.
     *
     * @param list<string> $times
     */
    #[DataProvider('entries')]
    public function testTheEntriesOfAVisitAreOneSessionADay(array $times, string $hours): void
    {
        $entries = array_map(static fn (string $time): string => '2026-11-07 ' . $time, $times);
        $event = $this->parse([...self::ROW, 'Sessions' => implode(', ', [...$entries, '2026-11-08 14:00'])]);

        self::assertNotNull($event);
        self::assertCount(2, $event->timesheets);
        [$visit, $sunday] = $event->timesheets;
        self::assertSame('2026-11-07', $visit->startAt?->format('Y-m-d'));
        self::assertSame($times[0], $visit->startTime?->format('H:i'));
        self::assertNull($visit->endTime);
        self::assertSame($hours, $visit->hours);
        self::assertSame('14:00', $sunday->startTime?->format('H:i'));
        self::assertNull($sunday->hours);
    }

    /**
     * The Cleaner keeps the label as it is: "puis…" is no slot it would read back into times.
     */
    public function testTheLabelOfTheEntriesIsNoSlot(): void
    {
        self::assertNull(HoursLabel::parse("puis toutes les 15 min jusqu'à 18h45"));
    }

    public function testASwissPlanIsPricedInFrancs(): void
    {
        $event = $this->parse([...self::ROW, 'Country' => 'Switzerland', 'Currency' => 'CHF', 'Ticket prices' => '35.00 CHF, 45.00 CHF']);

        self::assertSame('CH', $event?->place?->country?->code);
        self::assertSame('De 35 CHF à 45 CHF', $event?->prices);
    }

    public function testNullCoordinatesAreNone(): void
    {
        $event = $this->parse([...self::ROW, 'Coordinates' => '(0.0000000; 0.0000000)']);

        self::assertNull($event?->place?->latitude);
        self::assertNull($event?->place?->longitude);
    }

    /**
     * @param array<string, string> $row
     */
    private function parse(array $row): ?EventDto
    {
        $ref = new ReflectionClass(FeverImpactParser::class);

        return $ref->getMethod('arrayToDto')->invoke($ref->newInstanceWithoutConstructor(), $row);
    }
}
