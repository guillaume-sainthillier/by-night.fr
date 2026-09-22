<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Utils;

use MessageFormatter;
use RuntimeException;
use Transliterator;

/**
 * Puts "à" or "de" in front of a place name (a city or a venue) the way French does it:
 * the preposition contracts with a leading "Le"/"Les" ("au Mans", "aux Abymes",
 * "du Bikini", "des Abattoirs") and "de" elides before a vowel ("d'Angers", "d'Évry").
 * "La" and "L'" never contract: "à La Rochelle", "de L'Isle-Jourdain".
 */
final class FrenchPreposition
{
    /**
     * The grammar, as ICU select messages over how the name starts (see self::classify()).
     */
    private const array PATTERNS = [
        'à' => '{start, select, le {au {rest}} les {aux {rest}} other {à {name}}}',
        'de' => "{start, select, le {du {rest}} les {des {rest}} vowel {d''{name}} other {de {name}}}",
    ];

    /** @var array<string, MessageFormatter> */
    private static array $formatters = [];

    private static ?Transliterator $initialsFolder = null;

    public static function at(string $name): string
    {
        return self::format('à', $name);
    }

    public static function of(string $name): string
    {
        return self::format('de', $name);
    }

    private static function format(string $preposition, string $name): string
    {
        $name = trim($name);
        [$start, $rest] = self::classify($name);
        $formatter = self::$formatters[$preposition] ??= new MessageFormatter('fr', self::PATTERNS[$preposition]);

        return $formatter->format(['start' => $start, 'name' => $name, 'rest' => $rest])
            ?: throw new RuntimeException($formatter->getErrorMessage());
    }

    /**
     * @return array{'le'|'les'|'vowel'|'other', string} how the name starts, and the name without the article that contracts
     */
    private static function classify(string $name): array
    {
        if (1 === preg_match('/^(les?)\s+(\S.*)$/iu', $name, $matches)) {
            return ['le' === mb_strtolower($matches[1]) ? 'le' : 'les', $matches[2]];
        }

        // Accents and ligatures folded to ASCII: "Év" → "ev", "Œu" → "oeu"
        $initials = (self::$initialsFolder ??= Transliterator::create('Any-Latin; Latin-ASCII; Any-Lower'))
            ?->transliterate(mb_substr($name, 0, 2));

        // "Y" is a vowel before a consonant (Yvetot) and a consonant before a vowel (Yutz).
        // "H" is never elided: it is silent in Hendaye but aspirated in Haguenau, which only a dictionary knows;
        // a missing elision ("de Hendaye") reads as formal, an unwanted one ("d'Haguenau") as a mistake.
        if (\is_string($initials) && 1 === preg_match('/^(?:[aeiou]|y[^aeiouy])/', $initials)) {
            return ['vowel', $name];
        }

        return ['other', $name];
    }
}
