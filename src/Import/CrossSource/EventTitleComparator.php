<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Import\CrossSource;

use App\Utils\SluggerUtils;

/**
 * Tells whether two sources name the same show, for two events already known to be at the same venue on the same
 * day. Built to never merge two shows: a pair it is unsure of is turned down, a duplicate left apart is only a
 * duplicate, two shows merged lose one of them.
 *
 * The titles are compared as sets of words, once the noise the sources add is gone: status ("complet", "report"),
 * marketing ("tournée 2026", "en concert"), the venue and the town ("au Zénith de Toulouse").
 */
final class EventTitleComparator
{
    /** Said of every show, or of its sale: no help telling two shows apart */
    private const array NOISE = [
        // French and English stop words
        'le', 'la', 'les', 'de', 'des', 'du', 'et', 'au', 'aux', 'un', 'une', 'sur', 'pour', 'chez', 'avec', 'par', 'en',
        'dans', 'ou', 'son', 'sa', 'ses', 'the', 'of', 'and', 'in', 'at', 'by', 'on', 'to', 'for', 'with', 'no',
        // The sale, the status
        'complet', 'report', 'reporte', 'annule', 'nouvelle', 'nouveau', 'nouvel', 'date', 'dates', 'supplementaire',
        'derniere', 'dernieres', 'unique', 'exceptionnel', 'exceptionnelle', 'evenement', 'billet', 'billets',
        'billetterie', 'reservation',
        // Marketing
        'tournee', 'tour', 'world', 'live', 'concert', 'concerts', 'spectacle', 'spectacles', 'show', 'presente',
        'presentent', 'feat', 'ft', 'vs', 'saison', 'edition', 'version',
    ];

    /**
     * What kind of outing it is. Shared by unrelated shows, so they never name one on their own, and one more of
     * them on one side turns it into another outing ("Visite guidée de l'exposition Picasso" is not "Picasso").
     */
    private const array FORMATS = [
        'visite', 'visites', 'guidee', 'guidees', 'guide', 'atelier', 'ateliers', 'exposition', 'expositions', 'expo',
        'conference', 'conferences', 'projection', 'film', 'cinema', 'lecture', 'lectures', 'rencontre', 'rencontres',
        'stage', 'cours', 'initiation', 'balade', 'balades', 'randonnee', 'jeu', 'jeux', 'animation', 'animations',
        'salon', 'brocante', 'vide', 'grenier', 'loto', 'bal', 'fete', 'fetes', 'repas', 'degustation', 'theatre',
        'danse', 'musique', 'musiques', 'cirque', 'humour', 'enfant', 'enfants', 'famille', 'familles', 'jeune',
        'jeunes', 'public', 'festival', 'marche', 'noel', 'soiree', 'nuit', 'journee', 'journees', 'patrimoine',
        'europeennes', 'decouverte', 'ouverture', 'portes', 'ouvertes', 'apero', 'dj', 'set', 'karaoke', 'quiz',
        'blind', 'test', 'open', 'mic', 'jam', 'session', 'scene', 'contes', 'conte', 'messe', 'office',
        'ballet', 'opera', 'comedie', 'musicale', 'one', 'man', 'woman',
    ];

    /** Sold beside the show by the ticketing sites, under its title */
    private const array PRODUCTS = [
        'parking', 'pass', 'forfait', 'abonnement', 'vip', 'package', 'pack', 'hotel', 'navette', 'premium',
        'hospitalite', 'hospitality', 'upgrade', 'camping', 'coffret', 'cadeau', 'voucher', 'bracelet', 'consigne',
        'carre', 'meet', 'greet', 'privilege', 'prestige', 'loge', 'dinerspectacle', 'prestation',
    ];

    /** Another band plays the music of the one named */
    private const array TRIBUTES = [
        'hommage', 'tribute', 'tribut', 'cover', 'covers', 'symphonic', 'symphonique', 'symphony', 'orchestre',
        'orchestra', 'philharmonique', 'philharmonic', 'revival', 'celebration', 'celebrating', 'legacy', 'plays',
        'chante', 'chantent', 'sings', 'candlelight', 'univers',
    ];

    /** Another day of a festival, as the numbers of its parts */
    private const array DAYS = [
        'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche',
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /** A date written in the title ("04 décembre") says when, not what */
    private const string DATE_PATTERN = '/(?:^|-)\d{1,2}-(?:janvier|fevrier|mars|avril|mai|juin|juillet|aout|septembre|octobre|novembre|decembre)(?=-|$)/';

    /** The shortest a lone word can be to name a show on its own ("Stromae", not "Muse") */
    private const int MIN_LONE_WORD_LENGTH = 5;

    /**
     * @param list<string|null> $context the venue and the town, said by some titles and not by others
     */
    public function compare(string $left, string $right, array $context = []): MatchVerdict
    {
        $contextWords = [];
        foreach ($context as $name) {
            foreach ($this->words((string) $name) as $word) {
                $contextWords[$word] = true;
            }
        }

        $a = $this->tokens($left, $contextWords);
        $b = $this->tokens($right, $contextWords);

        if (self::subset($a, self::PRODUCTS) !== self::subset($b, self::PRODUCTS)) {
            return MatchVerdict::OtherProduct;
        }

        if (([] === self::subset($a, self::TRIBUTES)) !== ([] === self::subset($b, self::TRIBUTES))) {
            return MatchVerdict::Tribute;
        }

        if (self::numbers($a) !== self::numbers($b)) {
            return MatchVerdict::OtherNumber;
        }

        $distinctiveA = self::distinctive($a);
        $distinctiveB = self::distinctive($b);
        if ([] === $distinctiveA || [] === $distinctiveB) {
            return MatchVerdict::Generic;
        }

        if ($a === $b) {
            return MatchVerdict::Same;
        }

        [$short, $long] = \count($a) <= \count($b) ? [$a, $b] : [$b, $a];
        if ([] !== array_diff($short, $long)) {
            return MatchVerdict::Different;
        }

        // The longer title names another outing around the same name: a visit, a workshop, a kids' version
        if ([] !== self::subset(array_diff($long, $short), self::FORMATS)) {
            return MatchVerdict::Different;
        }

        $distinctiveShort = self::distinctive($short);
        $distinctiveLong = self::distinctive($long);

        // The shorter title must name the show on its own, and say at least half of what the longer one does
        if (\count($distinctiveShort) * 2 < \count($distinctiveLong)) {
            return MatchVerdict::Different;
        }

        if (1 === \count($distinctiveShort) && mb_strlen($distinctiveShort[0]) < self::MIN_LONE_WORD_LENGTH) {
            return MatchVerdict::Different;
        }

        return MatchVerdict::Contained;
    }

    /**
     * The words of a title once the noise is gone, sorted and unique.
     *
     * @param array<string, true> $contextWords
     *
     * @return list<string>
     */
    private function tokens(string $title, array $contextWords): array
    {
        $tokens = [];
        foreach ($this->words($title) as $word) {
            // A year names the season, not the show
            if (1 === preg_match('/^(19|20)\d\d$/', $word)) {
                continue;
            }

            // "1er", "2ème", "3rd": the number they stand for
            if (1 === preg_match('/^(\d+)(?:er|ere|eme|e|nd|rd|th|st)$/', $word, $matches)) {
                $word = $matches[1];
            }

            if (isset($contextWords[$word]) || \in_array($word, self::NOISE, true)) {
                continue;
            }

            $tokens[$word] = true;
        }

        $tokens = array_keys($tokens);
        sort($tokens);

        return array_map(strval(...), $tokens);
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        // Some feeds leave their HTML entities in ("BIGFLO &amp; OLI")
        $text = html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $slug = SluggerUtils::generateSlug(str_replace(['n°', 'N°', '°'], [' numero ', ' numero ', ' '], $text));

        // The opening act is not the show
        $slug = (string) preg_replace(['/(?:^|-)(?:1-?ere|premiere|1st)-partie(?=-|$)/', self::DATE_PATTERN], '-', $slug);

        // A dinner show is sold apart from the show, a "Dîner de cons" is a play
        $slug = str_replace('diner-spectacle', 'dinerspectacle', $slug);

        // A lone letter is what an elision leaves ("l'été", "d'ailleurs")
        return array_values(array_filter(
            explode('-', $slug),
            static fn (string $word): bool => mb_strlen($word) > 1 || ctype_digit($word),
        ));
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private static function distinctive(array $tokens): array
    {
        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => !ctype_digit($token)
                && !\in_array($token, self::FORMATS, true)
                && !\in_array($token, self::DAYS, true),
        ));
    }

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private static function numbers(array $tokens): array
    {
        return array_values(array_filter(
            $tokens,
            static fn (string $token): bool => ctype_digit($token) || \in_array($token, self::DAYS, true),
        ));
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $words
     *
     * @return list<string>
     */
    private static function subset(array $tokens, array $words): array
    {
        return array_values(array_intersect($tokens, $words));
    }
}
