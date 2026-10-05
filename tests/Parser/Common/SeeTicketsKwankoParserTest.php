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
use App\Parser\Common\SeeTicketsKwankoParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The Kwanko feed moved its product columns under a "tickets|" group in mid-2026: read with
 * the bare names, every row lacked a venue and was dropped, so nothing was imported.
 */
final class SeeTicketsKwankoParserTest extends TestCase
{
    /**
     * The header line as the feed serves it since mid-2026.
     */
    private const string GROUPED_HEADER = '"pid","pidSybiex","name","desc","category","merchant_category","imgurl","imgurl_tn","purl","tickets|eventDate","tickets|event_name","tickets|genre","tickets|venue_name","tickets|longitude","tickets|latitude","tickets|venue_address","tickets|venue_region","tickets|venue_department","tickets|max_price","tickets|min_price","tickets|event_location_city","tickets|available_from","tickets|onsale","tickets|primary_artist","tickets|secondary_artist","price|actualp"';

    /**
     * A row of the feed, in the order of GROUPED_HEADER: max_price at 18, min_price at 19.
     */
    private const array ROW = [
        '6761271', '6761271', 'Les 4 Saisons de Vivaldi', 'Un concert.', 'Concert', 'Tickets',
        'https://statics.digitick.com/vivaldi_300.jpg', 'https://statics.digitick.com/vivaldi_110.jpg',
        'https://pfd.seetickets.com/?P1', '02/10/2026 20:45', 'Les 4 Saisons de Vivaldi', 'Classique',
        'Église Saint-Germain', '2.3339', '48.8539', '3 place Saint-Germain 75006 PARIS', 'Île-de-France',
        '75', '35.00', '25.00', 'PARIS', '01/06/2026 10:00', 'Vente en cours', '', '', '25.00',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function headerLayouts(): iterable
    {
        yield 'grouped columns (current feed)' => [self::GROUPED_HEADER];
        yield 'bare columns (feed before mid-2026)' => [str_replace(['tickets|', 'price|'], '', self::GROUPED_HEADER)];
    }

    #[DataProvider('headerLayouts')]
    public function testARowBecomesAnEventWhateverTheHeaderLayout(string $headerLine): void
    {
        $event = $this->parseRow($headerLine, self::ROW);

        self::assertNotNull($event);
        self::assertSame('6761271', $event->externalId);
        self::assertSame('Les 4 Saisons de Vivaldi', $event->name);
        self::assertSame('Classique', $event->type);
        self::assertSame('2026-10-02', $event->startDate?->format('Y-m-d'));
        self::assertNull($event->hours);
        self::assertSame('20:45', $event->startTime?->format('H:i'));
        self::assertSame('De 25€ à 35€', $event->prices);
        self::assertSame(EventStatus::fromStatusMessage('Vente en cours'), $event->status);
        self::assertSame('Église Saint-Germain', $event->place?->name);
        self::assertSame('3 place Saint-Germain', $event->place->street);
        self::assertSame('75006', $event->place->city?->postalCode);
        self::assertSame('PARIS', $event->place->city->name);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function zeroPrices(): iterable
    {
        yield 'no lowest price' => ['35.00', '0.00', '35€'];
        yield 'no price at all' => ['0', '0.00', null];
    }

    /**
     * An affiliate feed says 0 when it has no price, not when the entry is free: "De 0€ à 35€" would be one.
     */
    #[DataProvider('zeroPrices')]
    public function testAZeroPriceIsNoPrice(string $maxPrice, string $minPrice, ?string $prices): void
    {
        $row = self::ROW;
        $row[18] = $maxPrice;
        $row[19] = $minPrice;

        $event = $this->parseRow(self::GROUPED_HEADER, $row);

        self::assertNotNull($event);
        self::assertSame($prices, $event->prices);
    }

    public function testTheHeadlinerComesBeforeTheOtherArtistsOfTheBill(): void
    {
        $row = self::ROW;
        $row[23] = 'Seth';
        $row[24] = 'SETH|THE GREAT OLD ONES';

        $event = $this->parseRow(self::GROUPED_HEADER, $row);

        self::assertNotNull($event);
        self::assertSame(['Seth', 'SETH', 'THE GREAT OLD ONES'], $event->performers, 'The Cleaner drops the repeats');
    }

    public function testTheTimeOfTheShowIsKept(): void
    {
        self::assertSame('20:45', $this->parseRow(self::GROUPED_HEADER, self::ROW)?->startTime?->format('H:i'));
    }

    public function testARowWithoutArtistsHasNone(): void
    {
        self::assertSame([], $this->parseRow(self::GROUPED_HEADER, self::ROW)?->performers);
    }

    /**
     * @param list<string> $row
     */
    private function parseRow(string $headerLine, array $row): ?EventDto
    {
        $ref = new ReflectionClass(SeeTicketsKwankoParser::class);
        $parser = $ref->newInstanceWithoutConstructor();

        $headers = $ref->getMethod('normalizeHeaders')->invoke(null, str_getcsv($headerLine, ',', '"', ''));

        return $ref->getMethod('arrayToDto')->invoke($parser, array_combine($headers, $row));
    }
}
