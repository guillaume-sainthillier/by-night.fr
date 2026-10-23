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
use App\Outing\OutingChangeWording;
use App\Outing\OutingState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OutingChangeWordingTest extends TestCase
{
    #[DataProvider('provideSentences')]
    public function testEachChangeHasItsSentence(OutingChange $change, OutingState $known, OutingState $now, string $expected): void
    {
        self::assertSame($expected, OutingChangeWording::sentence($change, $known, $now));
    }

    /**
     * @return iterable<string, array{OutingChange, OutingState, OutingState, string}>
     */
    public static function provideSentences(): iterable
    {
        $saturday = new OutingState('2026-10-24', '2026-10-24', '20:30', 1, 'Le Bikini', 'Ramonville');
        $weekend = new OutingState('2026-10-30', '2026-11-01', null, 2, 'Le Zénith', 'Toulouse');

        yield 'cancelled' => [OutingChange::Cancelled, $saturday, $saturday, "L'événement est annulé."];
        yield 'removed' => [OutingChange::Removed, $saturday, $saturday, "Son organisateur ne l'annonce plus\u{a0}: renseignez-vous auprès de lui avant de vous déplacer."];
        yield 'postponed, no date yet' => [OutingChange::Postponed, $saturday, $saturday, "L'événement est reporté, à une date encore inconnue."];
        yield 'postponed to new dates' => [OutingChange::Postponed, $saturday, $weekend, "L'événement est reporté\u{a0}: du vendredi 30 octobre au dimanche 1 novembre 2026 (avant\u{a0}: le samedi 24 octobre 2026 à 20h30)."];
        yield 'dates' => [OutingChange::Dates, $weekend, $saturday, "Nouvelles dates\u{a0}: le samedi 24 octobre 2026 à 20h30 (avant\u{a0}: du vendredi 30 octobre au dimanche 1 novembre 2026)."];
        yield 'venue' => [OutingChange::Venue, $saturday, $weekend, "Nouveau lieu\u{a0}: Le Zénith, Toulouse (avant\u{a0}: Le Bikini, Ramonville)."];
        yield 'venue unknown before' => [OutingChange::Venue, new OutingState(), $weekend, "Nouveau lieu\u{a0}: Le Zénith, Toulouse (avant\u{a0}: un lieu encore inconnu)."];
        yield 'moved online' => [OutingChange::MovedOnline, $saturday, $saturday, "L'événement se tient désormais en ligne."];
    }
}
