<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\SearchRepository;

use Transliterator;

/**
 * Whether two events on the same dates around the same place are likely the same, by their names. The sources word a
 * title their own way: "Concert d'Orelsan au Zénith" and "ORELSAN", "La guerre des rides au festival d'Avignon 2026"
 * and "La guerre des rides". The names are compared by their distinctive words: not the stop words, nor the words any
 * event may hold (festival, concert, marché de noël, a month, a year), nor the names of their places and cities,
 * which a title often repeats ("NIGHT LIFE #7 - GURU CLUB", "… à Bordeaux").
 *
 * Measured on the 1 632 events members published since 2023, against the events around them on their dates: 557 had
 * a likely twin, all 526 with the same name among them, and about 9 in 10 of the twins it finds are the same event or
 * a sibling of the same organizer at the same venue on the same day.
 */
final class EventNameMatcher
{
    /**
     * Elasticsearch's french_stop (config/packages/fos_elastica.yaml).
     */
    private const array STOP_WORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'a', 'au', 'aux', 'en', 'et', 'un', 'une', 'pour', 'sur', 'par', 'avec', 'dans', 'ou'];

    /**
     * Words many events hold: sharing one tells nothing. Compared folded, and singular ("festivals" is "festival").
     */
    private const array GENERIC_WORDS = [
        'festival', 'concert', 'soiree', 'expo', 'exposition', 'marche', 'noel', 'spectacle', 'vernissage', 'finissage',
        'edition', 'club', 'party', 'live', 'dj', 'feat', 'salon', 'fete', 'musique', 'theatre', 'atelier', 'visite',
        'rencontre', 'conference', 'game', 'escape', 'show', 'tournee', 'nuit', 'nouvel', 'an', 'apero', 'brunch', 'bal',
        'danse', 'dansant', 'dansante', 'cour', 'stage', 'projection', 'cine', 'cinema', 'jeu', 'journee', 'semaine',
        'week', 'end', 'weekend', 'saison', 'off', 'special', 'speciale', 'grand', 'grande', 'petit', 'petite',
        'janvier', 'fevrier', 'mar', 'avril', 'mai', 'juin', 'juillet', 'aout', 'septembre', 'octobre', 'novembre',
        'decembre', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche',
    ];

    private ?Transliterator $transliterator = null;

    /**
     * @param list<string|null> $context   the names of the places and cities of both events
     * @param bool              $sameVenue whether the two events take place at the same venue (a kilometre apart at
     *                                     most): sharing half of their distinctive words is then enough
     */
    public function isLikelySame(string $name, string $other, array $context, bool $sameVenue): bool
    {
        $contextWords = [];
        foreach ($context as $value) {
            $contextWords = [...$contextWords, ...$this->words((string) $value)];
        }

        $words = $this->distinctiveWords($name, $contextWords);
        $otherWords = $this->distinctiveWords($other, $contextWords);

        if ([] === $words || [] === $otherWords) {
            // Nothing distinctive left ("Marché de Noël"): only the very same wording at the same venue
            $allWords = $this->words($name);
            $otherAllWords = $this->words($other);
            sort($allWords);
            sort($otherAllWords);

            return $sameVenue && [] !== $allWords && $allWords === $otherAllWords;
        }

        $shared = \count(array_intersect($words, $otherWords));
        $shorter = min(\count($words), \count($otherWords));
        // The share of the shorter name found in the other: "Orelsan" is all in "Concert d'Orelsan au Zénith"
        $overlap = $shared / $shorter;

        if ($sameVenue) {
            return $overlap >= 0.5 && ($shared >= 2 || $shared === $shorter);
        }

        // Farther (a venue the source places at the centre of its city, a few kilometres off): one word in common is
        // too little ("Festival Locombia : Nkumba System" and "Festival Locombia"), unless it is the whole of both names
        return $overlap >= 0.75 && ($shared >= 2 || 1 === \count(array_unique([...$words, ...$otherWords])));
    }

    /**
     * @param list<string> $contextWords
     *
     * @return list<string>
     */
    private function distinctiveWords(string $value, array $contextWords): array
    {
        $words = [];
        foreach ($this->words($value) as $word) {
            if (\in_array($word, $contextWords, true) || ctype_digit($word)) {
                continue;
            }

            $word = self::singular($word);
            if (!\in_array($word, self::GENERIC_WORDS, true)) {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }

    /**
     * The words of a name, folded and lowercased, without the elided articles ("l'", "d'"), the stop words and the
     * single letters.
     *
     * @return list<string>
     */
    private function words(string $value): array
    {
        $this->transliterator ??= Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
        $value = (string) $this->transliterator->transliterate($value);
        $value = (string) preg_replace("/\\b(?:[lmtnsjdc]|qu)'/", ' ', $value);
        preg_match_all('/[a-z0-9]+/', $value, $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            static fn (string $word): bool => \strlen($word) > 1 && !\in_array($word, self::STOP_WORDS, true),
        )));
    }

    private static function singular(string $word): string
    {
        return \strlen($word) > 3 && \in_array($word[-1], ['s', 'x'], true) ? substr($word, 0, -1) : $word;
    }
}
