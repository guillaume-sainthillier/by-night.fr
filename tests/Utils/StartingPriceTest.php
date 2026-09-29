<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\StartingPrice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The texts are the sources' own (dev copy of the database, 2026-09).
 */
final class StartingPriceTest extends TestCase
{
    #[DataProvider('providePrices')]
    public function testTheLowestPriceToGetIn(?string $prices, ?float $startingPrice): void
    {
        self::assertSame($startingPrice, StartingPrice::fromPrices($prices));
    }

    /**
     * @return iterable<string, array{string|null, float|null}>
     */
    public static function providePrices(): iterable
    {
        yield 'an affiliate price' => ['22€', 22.0];
        yield 'with cents' => ['27.5€', 27.5];
        yield 'a decimal comma' => ['27,50 €', 27.5];
        yield 'in words' => ['12 euros', 12.0];
        yield 'an affiliate range' => ['De 57.5€ à 123.5€', 57.5];
        yield 'the lowest, wherever it comes' => ['Sur place : 12€ - prévente (hors frais) : 10€', 10.0];
        yield 'free for some only' => ['10 € adultes, gratuit pour les enfants', 10.0];
        yield 'an invitation among the fares' => ['Tarif Plein : 27€ | Tarif Abonné.e : 24€ | Invitation Abonné.e : 0€', 24.0];

        yield 'free' => ['Gratuit', 0.0];
        yield 'free, with a condition' => ['Gratuit, sur inscription', 0.0];
        yield 'free entry' => ['Entrée libre', 0.0];
        yield 'zero' => ['0€', 0.0];

        yield 'no amount' => ['Sur inscription', null];
        yield 'a number without its currency' => ['Billet: 39', null];
        yield 'blank' => ['  ', null];
        yield 'none' => [null, null];
    }
}
