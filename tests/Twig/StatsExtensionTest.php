<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Twig;

use App\Twig\StatsExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatsExtensionTest extends TestCase
{
    #[DataProvider('provideCounts')]
    public function testFormatEventsCount(int $count, string $expected): void
    {
        self::assertSame($expected, StatsExtension::formatEventsCount($count));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function provideCounts(): iterable
    {
        yield 'singular below 2 millions' => [1_924_164, "1,9 million d'événements"];
        yield 'exact 2 millions is plural' => [2_000_000, "2 millions d'événements"];
        yield 'floors instead of rounding up' => [2_098_765, "2 millions d'événements"];
        yield 'keeps the 100K digit' => [2_134_567, "2,1 millions d'événements"];
        yield 'never rounds up past the next million' => [12_987_654, "12,9 millions d'événements"];
        // "mille" and plain numbers take no "de": the phrase used to read "19 mille d'événements"
        yield 'below a million, the whole count grouped by thousands' => [19_234, "19\u{202F}234 événements"];
        yield 'just below a million' => [999_999, "999\u{202F}999 événements"];
        yield 'a few' => [500, '500 événements'];
        yield 'singular' => [1, '1 événement'];
    }
}
