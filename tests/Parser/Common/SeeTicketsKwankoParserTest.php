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
        $row = [
            '6761271', '6761271', 'Les 4 Saisons de Vivaldi', 'Un concert.', 'Concert', 'Tickets',
            'https://statics.digitick.com/vivaldi_300.jpg', 'https://statics.digitick.com/vivaldi_110.jpg',
            'https://pfd.seetickets.com/?P1', '02/10/2026 20:45', 'Les 4 Saisons de Vivaldi', 'Classique',
            'Église Saint-Germain', '2.3339', '48.8539', '3 place Saint-Germain 75006 PARIS', 'Île-de-France',
            '75', '35.00', '25.00', 'PARIS', '01/06/2026 10:00', 'Vente en cours', '', '', '25.00',
        ];

        $event = $this->parseRow($headerLine, $row);

        self::assertNotNull($event);
        self::assertSame('6761271', $event->externalId);
        self::assertSame('Les 4 Saisons de Vivaldi', $event->name);
        self::assertSame('Classique', $event->type);
        self::assertSame('2026-10-02', $event->startDate?->format('Y-m-d'));
        self::assertSame('À 20h45', $event->hours);
        self::assertSame('De 25.00€ à 35.00€', $event->prices);
        self::assertSame(EventStatus::fromStatusMessage('Vente en cours'), $event->status);
        self::assertSame('Église Saint-Germain', $event->place?->name);
        self::assertSame('3 place Saint-Germain', $event->place->street);
        self::assertSame('75006', $event->place->city?->postalCode);
        self::assertSame('PARIS', $event->place->city->name);
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
