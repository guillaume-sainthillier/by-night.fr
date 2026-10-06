<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\PriceLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PriceLabelTest extends TestCase
{
    #[DataProvider('provideFreePrices')]
    public function testFreeEntry(string $prices): void
    {
        self::assertSame(['label' => 'Gratuit', 'free' => true], PriceLabel::fromPrices($prices));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFreePrices(): iterable
    {
        yield 'gratuit' => ['Gratuit'];
        yield 'entrée libre' => ['Entrée libre'];
        yield 'lower case, without accent' => ['entree libre'];
        yield 'accès libre' => ['Accès libre et gratuit'];
        yield 'with a condition' => ['Gratuit sur inscription'];
        yield 'zero amount' => ['0€ - Entrée libre'];
        yield 'zero amount alone' => ['0€'];
    }

    #[DataProvider('providePayingPrices')]
    public function testPayingEntry(string $prices, string $label): void
    {
        self::assertSame(['label' => $label, 'free' => false], PriceLabel::fromPrices($prices));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePayingPrices(): iterable
    {
        yield 'one price' => ['39€', "39\u{a0}€"];
        yield 'with cents' => ['27.5€', "27,50\u{a0}€"];
        yield 'a range' => ['De 39€ à 59€', "Dès 39\u{a0}€"];
        yield 'the lowest, wherever it comes' => ['7€ Tarif normal / 6€ Tarif réduit sur présentation d\'un justificatif', "Dès 6\u{a0}€"];
        yield 'the same price twice' => ['39€ sur place, 39€ en ligne', "39\u{a0}€"];
        yield 'paying for some only' => ['10 € adultes, gratuit pour les enfants', "10\u{a0}€"];
    }

    #[DataProvider('provideNotes')]
    public function testAShortNoteWithoutAmountIsItsOwnLabel(string $prices, string $label): void
    {
        self::assertSame(['label' => $label, 'free' => false], PriceLabel::fromPrices($prices));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideNotes(): iterable
    {
        yield 'as written' => ['Sur inscription', 'Sur inscription'];
        yield 'lower case' => ['payant', 'Payant'];
        yield 'upper case' => ['SUR INVITATION', 'Sur invitation'];
        yield 'trailing punctuation' => ['Sur inscription.', 'Sur inscription'];
    }

    #[DataProvider('provideUnsayablePrices')]
    public function testNoLabelWhenNothingShortCanBeSaid(string $prices): void
    {
        self::assertNull(PriceLabel::fromPrices($prices));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnsayablePrices(): iterable
    {
        yield 'a number without its currency' => ['Billet: 39'];
        yield 'a sentence' => ['Dans la limite des places disponibles'];
        yield 'a phone number' => ['Groupes information et réservation : 01 40 05 12 12 de 9h30 à 17h30'];
    }

    public function testAnotherSourceSellingForLessStartsTheLabel(): void
    {
        // The event says 45 €, another source of the show sells it for 39 € (EventFamilyResolver)
        self::assertSame(['label' => "Dès 39\u{a0}€", 'free' => false], PriceLabel::fromPrices('45€', 39.0));
        self::assertSame(['label' => "Dès 39\u{a0}€", 'free' => false], PriceLabel::fromPrices(null, 39.0), 'Its own price unknown');
        self::assertSame(['label' => "Dès 27,50\u{a0}€", 'free' => false], PriceLabel::fromPrices('Sur inscription', 27.5));
        self::assertSame(['label' => "Dès 39\u{a0}€", 'free' => false], PriceLabel::fromPrices('Gratuit', 39.0), 'A ticket is sold: not free');
    }

    public function testItsOwnPricesWhenNoneSellsForLess(): void
    {
        self::assertSame(['label' => "45\u{a0}€", 'free' => false], PriceLabel::fromPrices('45€', 45.0));
        self::assertSame(['label' => 'Gratuit', 'free' => true], PriceLabel::fromPrices('Gratuit', 0.0));
        self::assertNull(PriceLabel::fromPrices(null, null));
    }

    public function testNoPrices(): void
    {
        self::assertNull(PriceLabel::fromPrices(null));
        self::assertNull(PriceLabel::fromPrices('  '));
    }

    public function testFreeForSomeOnlyIsNotFree(): void
    {
        $label = PriceLabel::fromPrices('10 € adultes, gratuit pour les enfants');

        self::assertFalse($label['free'] ?? false);
    }
}
