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
use App\Handler\EventHandler;
use App\Parser\Common\SowProgParser;
use App\Tests\AppKernelTestCase;
use Override;
use Psr\Log\NullLogger;
use ReflectionMethod;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * One SowProg "eventDescription" record maps to one event with a timesheet per scheduled date.
 */
final class SowProgParserTest extends AppKernelTestCase
{
    private SowProgParser $parser;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SowProgParser(
            new NullLogger(),
            self::getContainer()->get(MessageBusInterface::class),
            self::getContainer()->get(EventHandler::class),
            new MockHttpClient(),
            'user',
            'password',
        );
    }

    public function testARecordMapsToAnEvent(): void
    {
        $event = $this->map(self::record());

        self::assertSame('Sow Prog', $event->fromData);
        self::assertSame('4242', $event->externalId);
        self::assertSame('Soirée Salsa', $event->name);
        self::assertSame('Concert', $event->type);
        self::assertSame('Salsa', $event->category?->name);
        self::assertSame('https://pro.sowprog.com/picture.jpg', $event->imageUrl, 'The picture is served over https');
        self::assertSame(['https://tickets.example.com/salsa'], $event->websiteContacts);
        self::assertSame('2026-10-01', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-10-01', $event->endDate?->format('Y-m-d'));
        self::assertSame('De 20h30 à 23h00', $event->hours);
        self::assertSame('Le Bikini', $event->place?->name);
        self::assertSame('Rue Hermès', $event->place->street);
        self::assertSame('31520', $event->place->city?->postalCode);
        self::assertSame('Ramonville-Saint-Agne', $event->place->city->name);
        self::assertSame('France', $event->place->country?->name);
        self::assertEqualsWithDelta(43.5465, $event->latitude, 0.0001);
    }

    public function testPricesKeepTheirCents(): void
    {
        $event = $this->map(self::record(['eventPrice' => [
            ['label' => 'Tarif plein', 'price' => 12.5, 'currency' => 'EUR'],
            ['label' => 'Tarif réduit', 'price' => 8, 'currency' => 'EUR'],
        ]]));

        self::assertSame('Tarif plein : 12.5€ - Tarif réduit : 8€', $event->prices);
    }

    public function testALabelEndingWithAColonIsNotFollowedByASecondOne(): void
    {
        // As served for a Paris Jazz Club concert: "Tarif concert à 21h : : 12€"
        $event = $this->map(self::record(['eventPrice' => [
            ['label' => 'Tarif concert à 21h :', 'price' => 12, 'currency' => 'EUR'],
        ]]));

        self::assertSame('Tarif concert à 21h : 12€', $event->prices);
    }

    public function testEachScheduledDateIsATimesheet(): void
    {
        $event = $this->map(self::record(['eventSchedule' => ['eventScheduleDate' => [
            self::schedule('2026-10-01', '20:30', '23:00'),
            self::schedule('2026-10-02', '18:00', '18:00'),
            self::schedule('2026-10-03', null, null),
        ]]]));

        self::assertSame(
            [['2026-10-01', 'De 20h30 à 23h00'], ['2026-10-02', 'À 18h00'], ['2026-10-03', null]],
            array_map(static fn ($timesheet): array => [$timesheet->startAt?->format('Y-m-d'), $timesheet->hours], $event->timesheets),
        );
        self::assertNull($event->hours, 'Several distinct schedules: no single summary');
    }

    /**
     * DataTourisme took its range from the first and last periods as served, in no
     * particular order, and 11,485 events ended before they started (b6f45115).
     */
    public function testTheEventSpansItsSchedulesWhateverTheirOrder(): void
    {
        $event = $this->map(self::record(['eventSchedule' => ['eventScheduleDate' => [
            self::schedule('2026-11-20', '20:30', '23:00'),
            self::schedule('2026-10-01', '20:30', '23:00'),
            self::schedule('2026-10-15', '20:30', '23:00'),
        ]]]));

        self::assertSame('2026-10-01', $event->startDate?->format('Y-m-d'));
        self::assertSame('2026-11-20', $event->endDate?->format('Y-m-d'));
    }

    public function testARecordWithoutScheduleIsLeftOut(): void
    {
        self::assertNull($this->invoke(self::record(['eventSchedule' => ['eventScheduleDate' => []]])));
        self::assertNull($this->invoke(self::record(['location' => null])));
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
        $event = new ReflectionMethod(SowProgParser::class, 'arrayToDto')->invoke($this->parser, $record);

        return $event;
    }

    /**
     * @return array<string, string|null>
     */
    private static function schedule(string $date, ?string $startHour, ?string $endHour): array
    {
        return ['date' => $date, 'endDate' => $date, 'startHour' => $startHour, 'endHour' => $endHour];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function record(array $overrides = []): array
    {
        return array_replace([
            'id' => 4242,
            'modificationDate' => 1_790_000_000_000,
            'event' => [
                'title' => 'Soirée Salsa',
                'description' => 'Une soirée pour danser.',
                'picture' => 'http://pro.sowprog.com/picture.jpg',
                'eventType' => ['label' => 'Concert'],
                'eventStyle' => ['label' => 'Salsa'],
            ],
            'location' => [
                'id' => 77,
                'name' => 'Le Bikini',
                'contact' => [
                    'addressLine1' => 'Rue Hermès',
                    'addressLine2' => null,
                    'zipCode' => '31520',
                    'city' => 'Ramonville-Saint-Agne',
                    'country' => 'France',
                    'lattitude' => '43.5465',
                    'longitude' => '1.4832',
                ],
            ],
            'eventSchedule' => ['eventScheduleDate' => [self::schedule('2026-10-01', '20:30', '23:00')]],
            'artist' => [],
            'eventPrice' => [],
            'ticketStore' => [['url' => 'https://tickets.example.com/salsa']],
        ], $overrides);
    }
}
