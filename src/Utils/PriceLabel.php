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
    /** Free entry, however a source words it */
    private const string FREE_PATTERN = '/\b(gratuite?|entr[ée]e libre|acc[èe]s libre|free)\b/iu';

    /** An amount in euros: "39€", "22.0000€", "27,50 €", "12 euros" */
    private const string AMOUNT_PATTERN = '/(\d+(?:[.,]\d+)?)\s*(?:€|euros?\b|eur\b)/iu';

    /** Longer, a text without an amount is a sentence rather than a badge */
    private const int MAX_NOTE_LENGTH = 24;

    /**
     * @return array{label: string, free: bool}|null null when nothing short enough can be said
     */
    public static function fromPrices(?string $prices): ?array
    {
        $prices = trim((string) $prices);
        if ('' === $prices) {
            return null;
        }

        $amounts = self::amounts($prices);

        // "10 € adultes, gratuit pour les enfants" is not a free event, "0€" is
        $paying = array_filter($amounts, static fn (float $amount): bool => $amount > 0);
        if ([] === $paying && ([] !== $amounts || 1 === preg_match(self::FREE_PATTERN, $prices))) {
            return ['label' => 'Gratuit', 'free' => true];
        }

        $label = self::summarize($prices, array_values($paying));

        return null === $label ? null : ['label' => $label, 'free' => false];
    }

    /**
     * @return list<float> the amounts in euros, in the order the text gives them
     */
    private static function amounts(string $prices): array
    {
        preg_match_all(self::AMOUNT_PATTERN, $prices, $matches);

        return array_map(static fn (string $amount): float => (float) str_replace(',', '.', $amount), $matches[1]);
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
