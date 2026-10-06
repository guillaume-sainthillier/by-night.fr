<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use NumberFormatter;

/**
 * The price badge of the event cards, from the free text the sources give for the prices of an event:
 * "Gratuit", "Entrée libre", "39€", "De 39€ à 59€", "7€ Tarif normal / 6€ Tarif réduit sur présentation…".
 */
final class PriceLabel
{
    /** Longer, a text without an amount is a sentence rather than a badge */
    private const int MAX_NOTE_LENGTH = 24;

    /**
     * @param float|null $startingPrice the event's starting price: when its family sells the show at another price than
     *                                  its own prices say (EventFamilyResolver), "Dès" that price
     *
     * @return array{label: string, free: bool}|null null when nothing short enough can be said
     */
    public static function fromPrices(?string $prices, ?float $startingPrice = null): ?array
    {
        $own = StartingPrice::fromPrices($prices);
        if (null !== $startingPrice && $startingPrice > 0 && $startingPrice !== $own) {
            return ['label' => 'Dès ' . self::formatAmount($startingPrice), 'free' => false];
        }

        // "10 € adultes, gratuit pour les enfants" is not a free event, "0€" is (StartingPrice)
        if (0.0 === $own) {
            return ['label' => 'Gratuit', 'free' => true];
        }

        $prices = trim((string) $prices);
        if ('' === $prices) {
            return null;
        }

        $label = self::summarize($prices, StartingPrice::payingAmounts($prices));

        return null === $label ? null : ['label' => $label, 'free' => false];
    }

    /**
     * The label of a paying event, or null to show no badge: its price ("39 €"), the lowest of its prices ("Dès 6 €"),
     * or without any amount, a short note on how to get in ("Sur inscription", "Payant").
     *
     * @param string      $prices  the text as the source wrote it, e.g. "7€ Tarif normal / 6€ Tarif réduit…"
     * @param list<float> $amounts its positive amounts in euros, in the order of the text, e.g. [7.0, 6.0]
     */
    private static function summarize(string $prices, array $amounts): ?string
    {
        if ([] !== $amounts) {
            $lowest = self::formatAmount(min($amounts));

            return 1 === \count(array_unique($amounts)) ? $lowest : 'Dès ' . $lowest;
        }

        // A number without its currency ("Billet: 39", "Atelier limité à 12 personnes") says too little
        $note = trim($prices, " \t\n\r\0\x0B.:-");
        if ('' === $note || mb_strlen($note) > self::MAX_NOTE_LENGTH || 1 === preg_match('/\d/', $note)) {
            return null;
        }

        return mb_ucfirst(mb_strtolower($note));
    }

    /**
     * "39 €", "27,50 €": the cents only when there are some.
     */
    private static function formatAmount(float $amount): string
    {
        $formatter = new NumberFormatter('fr_FR', NumberFormatter::CURRENCY);
        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, fmod($amount, 1.0) > 0 ? 2 : 0);

        return (string) $formatter->formatCurrency($amount, 'EUR');
    }
}
