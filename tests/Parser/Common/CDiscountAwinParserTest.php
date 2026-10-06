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
use App\Enum\EventStatus;
use App\Parser\Common\CDiscountAwinParser;
use App\Parser\Ticketmaster\TicketmasterPerformance;
use App\Parser\Ticketmaster\TicketmasterShow;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Override;
use ReflectionMethod;
use ReflectionProperty;

/**
 * One row per show, dated in custom_1 as "le 14/02/2027 à 20h" (whole hours only in the feed
 * of 2026-09-24), keyed by its Ticketmaster show id.
 */
final class CDiscountAwinParserTest extends AppKernelTestCase
{
    private CDiscountAwinParser $parser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = self::getContainer()->get(CDiscountAwinParser::class);
    }

    public function testARowMapsToAnEvent(): void
    {
        $event = $this->invoke(self::row());

        self::assertInstanceOf(EventDto::class, $event);
        self::assertSame('CDiscount', $event->fromData);
        self::assertSame('46008799592', $event->externalId);
        self::assertSame('Nico Moreno', $event->name);
        self::assertSame('2027-02-14 00:00', $event->startDate?->format('Y-m-d H:i'));
        self::assertSame('2027-02-14 00:00', $event->endDate?->format('Y-m-d H:i'));
        self::assertNull($event->hours);
        self::assertSame('20:00', $event->startTime?->format('H:i'));
        self::assertSame('39.9€', $event->prices);
        self::assertSame('Zénith', $event->place?->name);
        self::assertSame('11 avenue Raymond Badiou', $event->place->street);
        self::assertSame('Toulouse', $event->place->city?->name);
        self::assertSame('31300', $event->place->city->postalCode);
        self::assertSame('FR', $event->place->country?->code);
    }

    public function testTheSamePlaceGetsTheSameExternalId(): void
    {
        $first = $this->invoke(self::row());
        $second = $this->invoke(self::row(['merchant_product_id' => '46008799593', 'custom_1' => 'le 15/02/2027 à 18h']));

        self::assertNotNull($first?->place?->externalId);
        self::assertSame($first->place->externalId, $second?->place?->externalId);
    }

    public function testAZeroPriceIsNoPrice(): void
    {
        // An affiliate feed says 0 when it has no price, not when the entry is free
        $event = $this->invoke(self::row(['search_price' => '0.00']));

        self::assertInstanceOf(EventDto::class, $event);
        self::assertNull($event->prices);
    }

    public function testRowsThatCannotBeDatedOrPlacedAreLeftOut(): void
    {
        self::assertNull($this->invoke(self::row(['custom_6' => ''])), 'No venue');
        self::assertNull($this->invoke(self::row(['custom_1' => ''])), 'No date');
        self::assertNull($this->invoke(self::row(['custom_1' => 'Date à venir'])), 'No parsable date');
    }

    public function testTicketmasterGivesTheShowAllItsDatesItsPictureAndItsVenueCoordinates(): void
    {
        $this->setTicketmasterShows(['46008799592' => new TicketmasterShow(
            'https://s1.ticketm.net/dam/a/1/nico_TABLET_LANDSCAPE_LARGE_16_9.jpg',
            43.5517,
            1.4826,
            [self::performance('2027-02-14 20:00'), self::performance('2027-02-15 18:30'), self::performance('2027-03-01 20:00')],
        )]);

        $event = $this->invoke(self::row());

        self::assertInstanceOf(EventDto::class, $event);
        self::assertSame('https://s1.ticketm.net/dam/a/1/nico_TABLET_LANDSCAPE_LARGE_16_9.jpg', $event->imageUrl);
        self::assertSame(43.5517, $event->place?->latitude);
        self::assertSame(1.4826, $event->place->longitude);
        self::assertSame('2027-02-14 00:00', $event->startDate?->format('Y-m-d H:i'));
        self::assertSame('2027-03-01 00:00', $event->endDate?->format('Y-m-d H:i'));
        self::assertNull($event->startTime, 'Spanned over the sessions by the cleaner');
        self::assertSame(
            ['2027-02-14 20:00', '2027-02-15 18:30', '2027-03-01 20:00'],
            array_map(static fn (EventTimesheetDto $timesheet): string => \sprintf(
                '%s %s',
                $timesheet->startAt?->format('Y-m-d'),
                $timesheet->startTime?->format('H:i'),
            ), $event->timesheets),
        );
        self::assertSame('39.9€', $event->prices, 'Ticketmaster has no price');
        self::assertSame('Nico Moreno', $event->name);
    }

    public function testCancelledPerformancesAreNoSessions(): void
    {
        $this->setTicketmasterShows(['46008799592' => new TicketmasterShow(null, null, null, [
            self::performance('2027-02-14 20:00', 'cancelled'),
            self::performance('2027-02-15 20:00', 'offsale'),
            self::performance('2027-02-16 20:00', 'rescheduled'),
            self::performance('2027-02-17 20:00', 'onsale'),
            self::performance('2027-02-18 20:00', 'postponed'),
        ])]);

        $event = $this->invoke(self::row());

        self::assertSame('2027-02-15', $event?->startDate?->format('Y-m-d'));
        self::assertSame('2027-02-17', $event->endDate?->format('Y-m-d'));
        self::assertCount(3, $event->timesheets, 'Sold out or rescheduled, the show takes place');
        self::assertNull($event->status);
    }

    public function testAShowCancelledOnEveryDateIsCancelled(): void
    {
        $this->setTicketmasterShows(['46008799592' => new TicketmasterShow(null, null, null, [
            self::performance('2027-02-14 20:00', 'cancelled'),
            self::performance('2027-02-15 20:00', 'cancelled'),
        ])]);

        $event = $this->invoke(self::row());

        self::assertSame(EventStatus::Cancelled, $event?->status);
        self::assertSame('2027-02-14', $event->startDate?->format('Y-m-d'), "CDiscount's own date");
        self::assertSame([], $event->timesheets);
    }

    public function testAShowTicketmasterDoesNotKnowKeepsWhatCDiscountSays(): void
    {
        $this->setTicketmasterShows(['1' => new TicketmasterShow('https://s1.ticketm.net/other.jpg', 1.0, 2.0, [self::performance('2027-05-01 20:00')])]);

        $event = $this->invoke(self::row());

        self::assertSame('https://images2.productserve.com/?w=200&h=200&url=a.jpg', $event?->imageUrl);
        self::assertNull($event->place?->latitude);
        self::assertSame('2027-02-14', $event->startDate?->format('Y-m-d'));
        self::assertSame('20:00', $event->startTime?->format('H:i'));
        self::assertSame([], $event->timesheets);
        self::assertNull($event->status);
    }

    public function testAShowWithoutPictureOrCoordinatesKeepsCDiscountsOnes(): void
    {
        $this->setTicketmasterShows(['46008799592' => new TicketmasterShow(null, null, null, [self::performance('2027-02-14 20:00')])]);

        $event = $this->invoke(self::row());

        self::assertSame('https://images2.productserve.com/?w=200&h=200&url=a.jpg', $event?->imageUrl);
        self::assertNull($event->place?->latitude);
        self::assertCount(1, $event->timesheets);
    }

    /**
     * @param array<array-key, TicketmasterShow> $shows
     */
    private function setTicketmasterShows(array $shows): void
    {
        new ReflectionProperty(CDiscountAwinParser::class, 'ticketmasterShows')->setValue($this->parser, $shows);
    }

    private static function performance(string $dateTime, string $status = 'onsale'): TicketmasterPerformance
    {
        $moment = new DateTimeImmutable($dateTime);

        return new TicketmasterPerformance($moment->setTime(0, 0), DateTimeImmutable::createFromFormat('!H:i', $moment->format('H:i')) ?: null, $status);
    }

    private function invoke(array $row): ?EventDto
    {
        /** @var EventDto|null $event */
        $event = new ReflectionMethod(CDiscountAwinParser::class, 'arrayToDto')->invoke($this->parser, $row);

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
            'aw_deep_link' => 'https://www.awin1.com/pclick.php?p=46008799592&a=660995&m=19627',
            'aw_image_url' => 'https://images2.productserve.com/?w=200&h=200&url=a.jpg',
            'merchant_product_id' => '46008799592',
            'product_name' => 'Nico Moreno',
            'description' => 'Techno.',
            'search_price' => '39.90', // as served: two decimals
            'custom_1' => 'le 14/02/2027 à 20h',
            'custom_2' => 'Toulouse',
            'custom_3' => '31300',
            'custom_4' => '11 avenue Raymond Badiou',
            'custom_6' => 'Zénith',
        ], $overrides);
    }
}
