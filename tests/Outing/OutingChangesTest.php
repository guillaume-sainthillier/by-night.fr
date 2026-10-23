<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Outing;

use App\Outing\OutingChange;
use App\Outing\OutingChanges;
use App\Outing\OutingState;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutingChangesTest extends TestCase
{
    private const string TODAY = '2026-10-22';

    /**
     * @param array<string, mixed> $known    what the member knew, over a concert on Saturday at 20:30 at Le Bikini
     * @param array<string, mixed> $now      the event now, over the same
     * @param list<OutingChange>   $expected
     */
    #[DataProvider('provideChanges')]
    public function testTheChangesThatChangeTheMembersPlans(array $known, array $now, array $expected): void
    {
        $saturday = ['start' => '2026-10-24', 'end' => '2026-10-24', 'time' => '20:30', 'place' => 1, 'venue' => 'Le Bikini', 'city' => 'Ramonville', 'status' => null];

        self::assertSame($expected, OutingChanges::between(
            OutingState::fromArray([...$saturday, ...$known]),
            OutingState::fromArray([...$saturday, ...$now]),
            new DateTimeImmutable(self::TODAY),
        ));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>, list<OutingChange>}>
     */
    public static function provideChanges(): iterable
    {
        yield 'nothing' => [[], [], []];
        yield 'cancelled' => [[], ['status' => 'cancelled'], [OutingChange::Cancelled]];
        yield 'still cancelled' => [['status' => 'cancelled'], ['status' => 'cancelled'], []];
        yield 'removed by its source' => [[], ['status' => 'removed'], [OutingChange::Removed]];
        yield 'moved online' => [[], ['status' => 'moved_online'], [OutingChange::MovedOnline]];
        yield 'sold out, the member may have their ticket' => [[], ['status' => 'sold_out'], []];
        yield 'taking place again' => [['status' => 'postponed'], [], []];
        yield 'a day later' => [[], ['start' => '2026-10-25', 'end' => '2026-10-25'], [OutingChange::Dates]];
        yield 'a day earlier' => [[], ['start' => '2026-10-23', 'end' => '2026-10-23'], [OutingChange::Dates]];
        yield 'another time' => [[], ['time' => '21:00'], [OutingChange::Dates]];
        yield 'a time given at last' => [['time' => null], [], []];
        yield 'more days' => [[], ['start' => '2026-10-23', 'end' => '2026-10-31'], []];
        yield 'postponed says the dates changed' => [[], ['status' => 'postponed', 'start' => '2026-11-24', 'end' => '2026-11-24'], [OutingChange::Postponed]];
        yield 'rescheduled says the dates changed' => [[], ['status' => 'rescheduled', 'start' => '2026-11-24', 'end' => '2026-11-24'], [OutingChange::Rescheduled]];
        yield 'another venue' => [[], ['place' => 2, 'venue' => 'Le Zénith', 'city' => 'Toulouse'], [OutingChange::Venue]];
        yield 'the same venue, merged' => [[], ['place' => 2, 'venue' => 'LE BIKINI ', 'city' => 'Ramonville'], []];
        yield 'a venue of the same name elsewhere' => [[], ['place' => 2, 'city' => 'Toulouse'], [OutingChange::Venue]];
        yield 'cancelled and moved, the most serious first' => [[], ['place' => 2, 'venue' => 'Le Zénith', 'status' => 'cancelled'], [OutingChange::Cancelled, OutingChange::Venue]];
        yield 'begun: the sessions gone by dropped' => [['start' => '2026-09-01', 'end' => '2026-12-31'], ['start' => '2026-10-23', 'end' => '2026-12-31'], []];
        yield 'begun: closing earlier' => [['start' => '2026-09-01', 'end' => '2026-12-31'], ['start' => '2026-09-01', 'end' => '2026-11-30'], [OutingChange::Dates]];
        yield 'already over' => [['start' => '2026-10-20', 'end' => '2026-10-20'], ['start' => '2026-10-20', 'end' => '2026-10-20', 'status' => 'cancelled'], []];
        yield 'today is not over' => [['start' => self::TODAY, 'end' => self::TODAY], ['start' => self::TODAY, 'end' => self::TODAY, 'status' => 'cancelled'], [OutingChange::Cancelled]];
    }

    public function testAStateKeepsWhatItStored(): void
    {
        $state = new OutingState('2026-10-24', '2026-10-25', '20:30', 1, 'Le Bikini', 'Ramonville', 'cancelled');

        self::assertEquals($state, OutingState::fromArray($state->toArray()));
        self::assertEquals(new OutingState(), OutingState::fromArray(['start' => '', 'place' => 'x']));
    }
}
