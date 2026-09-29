<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

/**
 * The lowest price to get in, from the free text the sources give for the prices of an event: what the agenda filters
 * on (Event::$startingPrice) and the price badge of the cards shows (PriceLabel).
 */
final class StartingPrice
{
    /** Free entry, however a source words it */
    private const string FREE_PATTERN = '/\b(gratuite?|entr[ée]e libre|acc[èe]s libre|free)\b/iu';

    /** An amount in euros: "39€", "22.0000€", "27,50 €", "12 euros" */
    private const string AMOUNT_PATTERN = '/(\d+(?:[.,]\d+)?)\s*(?:€|euros?\b|eur\b)/iu';

    /**
     * 0 for a free entry, the lowest amount to pay, or null when the text names neither ("Sur inscription"): the
     * price is unknown, not free.
     *
     * A free fare among paying ones does not make the event free: "10 € adultes, gratuit pour les enfants" starts at
     * 10 €, as "Tarif plein : 27€ | Invitation abonné.e : 0€" starts at 27 €.
     */
    public static function fromPrices(?string $prices): ?float
    {
        $prices = trim((string) $prices);
        if ('' === $prices) {
            return null;
        }

        $paying = self::payingAmounts($prices);
        if ([] !== $paying) {
            return min($paying);
        }

        // "0€", or the free entry in words
        return [] !== self::amounts($prices) || 1 === preg_match(self::FREE_PATTERN, $prices) ? 0.0 : null;
    }

    /**
     * @return list<float> the positive amounts in euros, in the order the text gives them
     */
    public static function payingAmounts(string $prices): array
    {
        return array_values(array_filter(self::amounts($prices), static fn (float $amount): bool => $amount > 0));
    }

    /**
     * @return list<float> the amounts in euros, in the order the text gives them
     */
    private static function amounts(string $prices): array
    {
        preg_match_all(self::AMOUNT_PATTERN, $prices, $matches);

        return array_map(static fn (string $amount): float => (float) str_replace(',', '.', $amount), $matches[1]);
    }
}
