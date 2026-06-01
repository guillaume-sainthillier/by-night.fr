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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Contracts\Cache\CacheInterface;

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
        self::assertSame('À 13h00', $event->hours);
        self::assertSame('2026-07-25', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-07-25', $event->endDate?->format('Y-m-d'));
        self::assertCount(1, $event->timesheets);
        self::assertSame('2026-07-25', $event->timesheets[0]->startAt?->format('Y-m-d'));
        self::assertSame('À 13h00', $event->timesheets[0]->hours, 'The timesheet carries the showtime.');
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
        // Each date keeps its own showtime, even though the event-level label is dropped.
        self::assertSame(['À 20h30', 'À 20h30', 'À 18h00'], array_map(
            static fn ($timesheet): ?string => $timesheet->hours,
            $event->timesheets,
        ));
        self::assertSame('De 15€ à 25€', $event->prices, 'Price range spans every ticket tier.');
        self::assertSame('2026-08-01', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-08-03', $event->endDate?->format('Y-m-d'));
        self::assertNull($event->hours, 'No single event-level hours when showtimes differ.');
    }

    public function testSameDateWithDifferentShowtimesCollapsesToOneTimesheet(): void
    {
        // Timesheets are stored per date, so two showtimes on the same day collapse
        // into a single timesheet that keeps the first-seen showtime label.
        $rows = [
            $this->row('50000001', 'Matinée', '12', '2026-09-01', '15:00'),
            $this->row('50000002', 'Matinée', '12', '2026-09-01', '20:00'),
        ];

        $events = $this->groupEvents($rows);

        self::assertCount(1, $events);
        self::assertCount(1, $events[0]->timesheets, 'One timesheet per date, regardless of showtimes.');
        self::assertSame('À 15h00', $events[0]->timesheets[0]->hours);
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

    /**
     * @param list<array<string, string>> $rows
     *
     * @return list<EventDto>
     */
    private function groupEvents(array $rows): array
    {
        $ref = new ReflectionClass(FnacSpectaclesAwinParser::class);

        // Build the parser without its container dependencies and stub the cache so
        // image-url resolution never touches the network.
        $parser = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('cache')->setValue($parser, $this->stubCache());

        /** @var list<EventDto> $events */
        $events = $ref->getMethod('groupEvents')->invoke($parser, $rows);

        return $events;
    }

    private function stubCache(): CacheInterface
    {
        return new class implements CacheInterface {
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                return 'https://image.example/poster.jpg';
            }

            public function delete(string $key): bool
            {
                return true;
            }
        };
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
