<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\HoursLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HoursLabelTest extends TestCase
{
    /**
     * @param array{0: string, 1: string|null}|null $expected
     */
    #[DataProvider('labels')]
    public function testAPlainSlotIsReadBackIntoItsTimes(?string $label, ?array $expected): void
    {
        $slot = HoursLabel::parse($label);

        self::assertSame($expected, null === $slot ? null : [$slot[0]->format('H:i'), $slot[1]?->format('H:i')]);
    }

    /**
     * @return iterable<string, array{0: string|null, 1: array{0: string, 1: string|null}|null}>
     */
    public static function labels(): iterable
    {
        // What the parsers wrote
        yield 'OpenAgenda' => ['À 20h30', ['20:30', null]];
        yield 'OpenAgenda slot' => ['De 10h00 à 18h00', ['10:00', '18:00']];
        yield 'Bikini' => ['À 20:30', ['20:30', null]];
        yield 'CDiscount' => ['À 20h', ['20:00', null]];
        yield 'capital A, no accent' => ['A 20h00', ['20:00', null]];
        yield 'past midnight' => ['De 21h00 à 00h00', ['21:00', '00:00']];

        // What the organizers and the Toulouse feed write
        yield 'bare time' => ['20h', ['20:00', null]];
        yield 'bare time with minutes' => ['20h30', ['20:30', null]];
        yield 'upper case' => ['18H30', ['18:30', null]];
        yield 'final full stop' => ['A 20h30.', ['20:30', null]];
        yield 'dashed slot' => ['21h-05h', ['21:00', '05:00']];
        yield 'spaced dashed slot' => ['20:00 - 05:00', ['20:00', '05:00']];
        yield 'slashed slot' => ['20H30/05H', ['20:30', '05:00']];
        yield 'slot without de' => ['23:00 à 06:00', ['23:00', '06:00']];
        yield 'words' => ['de 21h à minuit', ['21:00', '00:00']];
        yield 'noon' => ['À midi', ['12:00', null]];
        yield 'spaces in the time' => ['De 9 h à 12 h 30', ['09:00', '12:30']];
        yield '24h is midnight' => ['De 20h à 24h', ['20:00', '00:00']];

        // Not a slot: kept as a label
        yield 'empty' => ['', null];
        yield 'none' => [null, null];
        yield 'from' => ['À partir de 19h30', null];
        yield 'day' => ['Jeudi à 20h', null];
        yield 'two slots' => ['À 20h, de 21h à minuit', null];
        yield 'two times' => ['10h45 et 16h30', null];
        yield 'repeated' => ['A 15h. A 15h.', null];
        yield 'no such hour' => ['À 25h', null];
        yield 'no such minute' => ['À 20h75', null];
        yield 'opening hours' => ['Du mardi au dimanche de 10h à 18h.', null];
    }
}
