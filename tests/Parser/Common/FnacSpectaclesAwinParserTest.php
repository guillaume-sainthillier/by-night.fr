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
use App\Parser\Common\FnacSpectaclesAwinParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * The Fnac feed lists one CSV row per ticket product, so a single show appears as
 * many near-identical rows. {@see FnacSpectaclesAwinParser::groupEvents()} collapses
 * those into one event carrying a timesheet per distinct date.
 */
final class FnacSpectaclesAwinParserTest extends TestCase
{
    public function testDuplicateRowsWithSameDateCollapseIntoOneEvent(): void
    {
        // 20 ticket products for the same performance (the user-provided example).
        $rows = [];
        for ($pid = 21628777; $pid <= 21628796; ++$pid) {
            $rows[] = $this->row((string) $pid, "Vous n'aimez pas Van Gogh ?", '20.5', '2026-07-25', '13:00');
        }

        $events = $this->groupEvents($rows);

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertSame("Vous n'aimez pas Van Gogh ?", $event->name);
        self::assertSame('20.5€', $event->prices);
        self::assertNull($event->hours);
        self::assertSame('2026-07-25', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-07-25', $event->endDate?->format('Y-m-d'));
        self::assertCount(1, $event->timesheets);
        self::assertSame('2026-07-25', $event->timesheets[0]->startAt?->format('Y-m-d'));
        self::assertSame('13:00', $event->timesheets[0]->startTime?->format('H:i'), 'The timesheet carries the showtime.');
        // External id is the stable content hash, not a raw merchant product id.
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string) $event->externalId);
    }

    public function testSameShowAcrossSeveralDatesProducesMultipleTimesheets(): void
    {
        // Same show, three dates, two price tiers, one duplicated date.
        $rows = [
            $this->row('30000001', 'Le Cirque', '15', '2026-08-01', '20:30'),
            $this->row('30000002', 'Le Cirque', '25', '2026-08-01', '20:30'),
            $this->row('30000003', 'Le Cirque', '15', '2026-08-02', '20:30'),
            $this->row('30000004', 'Le Cirque', '15', '2026-08-03', '18:00'),
        ];

        $events = $this->groupEvents($rows);

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertCount(3, $event->timesheets, 'One timesheet per distinct date.');
        self::assertSame(['2026-08-01', '2026-08-02', '2026-08-03'], array_map(
            static fn ($timesheet): ?string => $timesheet->startAt?->format('Y-m-d'),
            $event->timesheets,
        ));
        // Each date keeps its own showtime
        self::assertSame(['20:30', '20:30', '18:00'], array_map(
            static fn ($timesheet): ?string => $timesheet->startTime?->format('H:i'),
            $event->timesheets,
        ));
        self::assertSame('De 15€ à 25€', $event->prices, 'Price range spans every ticket tier.');
        self::assertSame('2026-08-01', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-08-03', $event->endDate?->format('Y-m-d'));
        self::assertNull($event->hours);
    }

    public function testThePerformancesAreInChronologicalOrder(): void
    {
        $events = $this->groupEvents([
            $this->row('60000001', 'Matinée', '12', '2026-09-02', '11:00'),
            $this->row('60000002', 'Matinée', '12', '2026-09-01', '20:00'),
            $this->row('60000003', 'Matinée', '12', '2026-09-01', '15:00'),
        ]);

        self::assertSame(['2026-09-01 15:00', '2026-09-01 20:00', '2026-09-02 11:00'], array_map(
            static fn ($timesheet): string => $timesheet->startAt?->format('Y-m-d') . ' ' . $timesheet->startTime?->format('H:i'),
            $events[0]->timesheets,
        ));
        self::assertSame('2026-09-01', $events[0]->startDate?->format('Y-m-d'));
    }

    public function testTheEndOfTheSaleIsNoEndOfTheShow(): void
    {
        // valid_to is when Fnac stops selling the ticket, weeks after the performance: read as the end, as before
        // #407, it made "Le Roi Soleil" at the Zénith de Toulouse (one evening, 2027-06-12) run until 2027-07-04
        $row = $this->row('21528264', 'Le Roi Soleil - Tournée', '35.0', '2027-06-12', '20:30');
        $row['valid_to'] = '2027-07-04';

        $event = $this->groupEvents([$row])[0];

        self::assertSame('2027-06-12', $event->endDate?->format('Y-m-d'));
        self::assertCount(1, $event->timesheets);
        self::assertSame('2027-06-12', $event->timesheets[0]->startAt?->format('Y-m-d'));
        self::assertSame('2027-06-12', $event->timesheets[0]->endAt?->format('Y-m-d'));
    }

    public function testAZeroPriceIsNoPrice(): void
    {
        // The feed says 0 for a ticket it has no price for: fnac.com sells "Grévin - Billet Daté" at 22 € while a
        // row of it says 0, which made it "De 0€ à 22€" and its badge the dearest price only
        $rows = [
            $this->row('70000001', 'Grévin - Billet Daté', '0.0', '2026-10-01', '10:00'),
            $this->row('70000002', 'Grévin - Billet Daté', '22.0', '2026-10-01', '10:00'),
            $this->row('70000003', 'Grévin - Billet Daté', '27.5', '2026-10-02', '10:00'),
        ];

        self::assertSame('De 22€ à 27.5€', $this->groupEvents($rows)[0]->prices);
    }

    public function testAShowWithoutAnyPriceHasNone(): void
    {
        // Not "0€", which the event cards take for a free entry
        $rows = [
            $this->row('80000001', 'Le Tour du Monde en 80 Jours', '0.0', '2026-10-30', '20:00'),
            $this->row('80000002', 'Le Tour du Monde en 80 Jours', '0', '2026-10-31', '20:00'),
        ];

        self::assertNull($this->groupEvents($rows)[0]->prices);
    }

    public function testTwoShowtimesOfADayAreTwoSessions(): void
    {
        // The ticket tiers of a performance are rows of their own: one session per day and showtime
        $rows = [
            $this->row('50000001', 'Matinée', '12', '2026-09-01', '15:00'),
            $this->row('50000002', 'Matinée', '18', '2026-09-01', '15:00'),
            $this->row('50000003', 'Matinée', '12', '2026-09-01', '20:00'),
        ];

        $events = $this->groupEvents($rows);

        self::assertCount(1, $events);
        self::assertSame(['15:00', '20:00'], array_map(
            static fn ($timesheet): ?string => $timesheet->startTime?->format('H:i'),
            $events[0]->timesheets,
        ));
    }

    public function testRowsAtDifferentVenuesStaySeparate(): void
    {
        $rows = [
            $this->row('40000001', 'Concert', '30', '2026-09-01', '21:00', 'Zénith', 'Toulouse', '31000', '11 av X'),
            $this->row('40000002', 'Concert', '30', '2026-09-02', '21:00', 'Olympia', 'Paris', '75009', '28 bd Y'),
        ];

        $events = $this->groupEvents($rows);

        self::assertCount(2, $events, 'Same name at different places are different shows.');
    }

    public function testThePosterIsKeptAsServed(): void
    {
        $events = $this->groupEvents([$this->row('60000001', 'Affiche', '10', '2026-10-01', '20:00')]);

        self::assertSame('https://www.fnacspectacles.com/obj/poster_547641_4260525_222x222.jpg', $events[0]->imageUrl);
    }

    public static function providePlaceholders(): iterable
    {
        yield 'blank.gif' => ['https://www.fnacspectacles.com/obj/media/FR-eventim/teaser/blank.gif'];
        yield 'the name of the show' => ["https://www.fnacspectacles.com/obj/media/FR-eventim/teaser/Back to the 80's !"];
        yield 'nothing' => [''];
    }

    #[DataProvider('providePlaceholders')]
    public function testAPlaceholderIsNoPoster(string $placeholder): void
    {
        $row = $this->row('60000002', 'Sans affiche', '10', '2026-10-01', '20:00');
        $row['merchant_image_url'] = $placeholder;

        self::assertNull($this->groupEvents([$row])[0]->imageUrl);
    }

    /**
     * @param list<array<string, string>> $rows
     *
     * @return list<EventDto>
     */
    private function groupEvents(array $rows): array
    {
        $ref = new ReflectionClass(FnacSpectaclesAwinParser::class);

        // Built without its container dependencies: no HTTP client, so grouping a feed
        // cannot send a request.
        $parser = $ref->newInstanceWithoutConstructor();

        /** @var list<EventDto> $events */
        $events = $ref->getMethod('groupEvents')->invoke($parser, $rows);

        return $events;
    }

    /**
     * @return array<string, string>
     */
    private function row(
        string $productId,
        string $name,
        string $price,
        string $eventDate,
        string $time,
        string $venue = 'Théâtre Buffon',
        string $city = 'Avignon',
        string $postalCode = '84000',
        string $street = '101 rue de la Carreterie',
    ): array {
        return [
            'aw_deep_link' => \sprintf('https://www.awin1.com/pclick.php?p=%s&a=660995&m=12494', $productId),
            'product_name' => $name,
            'merchant_product_id' => $productId,
            'merchant_image_url' => 'https://www.fnacspectacles.com/obj/poster_547641_4260525_222x222.jpg',
            'description' => 'Comédie tout public',
            'search_price' => $price,
            'is_for_sale' => '1',
            'valid_to' => '2026-07-25',
            'product_short_description' => '',
            'custom_3' => $postalCode,
            'custom_4' => $street,
            'custom_5' => 'FR',
            'custom_7' => $time,
            'Tickets:venue_name' => $venue,
            'Tickets:venue_address' => $city,
            'Tickets:event_date' => $eventDate,
            'Tickets:latitude' => '43.95',
            'Tickets:longitude' => '4.82',
        ];
    }
}
