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
use App\Parser\Common\BilletsReducAwinParser;
use App\Tests\AppKernelTestCase;
use Override;
use ReflectionMethod;

/**
 * One row per show, its sessions in custom_3 as served by the feed:
 * [{"SessionDate":"2027-04-06T15:00:00","SoldOut":0,"Delayed":0}, …].
 */
final class BilletsReducAwinParserTest extends AppKernelTestCase
{
    private BilletsReducAwinParser $parser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = self::getContainer()->get(BilletsReducAwinParser::class);
    }

    public function testARowMapsToAnEvent(): void
    {
        $event = $this->map(self::row());

        self::assertSame('Billets Reduc', $event->fromData);
        self::assertSame('987654', $event->externalId);
        self::assertSame('Le Songe', $event->name);
        self::assertSame('2027-04-06 00:00', $event->startDate?->format('Y-m-d H:i'));
        self::assertSame('2027-04-07 00:00', $event->endDate?->format('Y-m-d H:i'), 'valid_to is the last session on sale');
        self::assertSame('À 20h30', $event->hours);
        self::assertSame('25.5€', $event->prices);
        self::assertSame('https://images.billetreduc.com/n800/987654.jpeg', $event->imageUrl, 'The 800px rendition');
        self::assertSame("Une pièce.<br />\n<br />\nDe Shakespeare.", $event->description);
        self::assertSame('Théâtre de la Huchette', $event->place?->name);
        self::assertSame('23 rue de la Huchette', $event->place->street);
        self::assertSame('PARIS 5EME', $event->place->city?->name);
        self::assertSame('75005', $event->place->city->postalCode);
        self::assertSame('FR', $event->place->country?->code);
    }

    public function testASoldOutSessionDoesNotOpenTheEvent(): void
    {
        $event = $this->map(self::row(['custom_3' => json_encode([
            ['SessionDate' => '2027-04-05T20:30:00', 'SoldOut' => 1, 'Delayed' => 0],
            ['SessionDate' => '2027-04-06T20:30:00', 'SoldOut' => 0, 'Delayed' => 0],
        ])]));

        self::assertSame('2027-04-06', $event->startDate?->format('Y-m-d'));
    }

    public function testSessionsAtSeveralTimesGiveNoSingleShowtime(): void
    {
        $event = $this->map(self::row(['custom_3' => json_encode([
            ['SessionDate' => '2027-04-06T15:00:00', 'SoldOut' => 0, 'Delayed' => 0],
            ['SessionDate' => '2027-04-07T20:30:00', 'SoldOut' => 0, 'Delayed' => 0],
        ])]));

        self::assertNull($event->hours);
    }

    public function testAPlaceholderStreetIsDropped(): void
    {
        self::assertNull($this->map(self::row(['Tickets:event_location_address' => '.']))->place?->street);
    }

    public function testAnUnparsableValidToEndsTheEventOnItsFirstDay(): void
    {
        $event = $this->map(self::row(['valid_to' => '']));

        self::assertSame('2027-04-06', $event->endDate?->format('Y-m-d'));
    }

    public function testRowsThatCannotBeListedAreLeftOut(): void
    {
        self::assertNull($this->invoke(self::row(['is_for_sale' => '0'])), 'Not for sale');
        self::assertNull($this->invoke(self::row(['Tickets:venue_name' => ' '])), 'No venue');
        self::assertNull($this->invoke(self::row(['custom_3' => '[]'])), 'No session');
        self::assertNull($this->invoke(self::row(['custom_3' => json_encode([
            ['SessionDate' => '2027-04-06T20:30:00', 'SoldOut' => 1, 'Delayed' => 0],
        ])])), 'Every session sold out');
    }

    private function map(array $row): EventDto
    {
        $event = $this->invoke($row);
        self::assertInstanceOf(EventDto::class, $event);

        return $event;
    }

    private function invoke(array $row): ?EventDto
    {
        /** @var EventDto|null $event */
        $event = new ReflectionMethod(BilletsReducAwinParser::class, 'arrayToDto')->invoke($this->parser, $row);

        return $event;
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private static function row(array $overrides = []): array
    {
        return array_replace([
            'aw_deep_link' => 'https://www.awin1.com/pclick.php?p=1&a=2&m=3',
            'merchant_product_id' => '987654',
            'product_name' => 'Le Songe',
            'description' => 'Une pièce.',
            'merchant_image_url' => 'https://images.billetreduc.com/n200/987654.jpeg',
            'valid_to' => '4/7/2027 12:00:00 AM',
            'search_price' => '25.5',
            'is_for_sale' => '1',
            'custom_1' => '75005',
            'custom_3' => json_encode([
                ['SessionDate' => '2027-04-06T20:30:00', 'SoldOut' => 0, 'Delayed' => 0],
                ['SessionDate' => '2027-04-07T20:30:00', 'SoldOut' => 0, 'Delayed' => 0],
            ]),
            'product_short_description' => 'De Shakespeare.',
            'Tickets:venue_name' => 'Théâtre de la Huchette',
            'Tickets:longitude' => '2.3469',
            'Tickets:latitude' => '48.8527',
            'Tickets:event_location_address' => '23 rue de la Huchette',
            'Tickets:event_location_city' => 'PARIS 5EME',
        ], $overrides);
    }
}
