<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\FrenchPreposition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrenchPrepositionTest extends TestCase
{
    #[DataProvider('provideNames')]
    public function testAt(string $name, string $expectedAt, string $expectedOf): void
    {
        self::assertSame($expectedAt, FrenchPreposition::at($name));
    }

    #[DataProvider('provideNames')]
    public function testOf(string $name, string $expectedAt, string $expectedOf): void
    {
        self::assertSame($expectedOf, FrenchPreposition::of($name));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideNames(): iterable
    {
        yield 'plain name' => ['Toulouse', 'à Toulouse', 'de Toulouse'];
        yield '"Le" contracts' => ['Le Mans', 'au Mans', 'du Mans'];
        yield '"Les" contracts' => ['Les Abymes', 'aux Abymes', 'des Abymes'];
        yield 'venue with "Le"' => ['Le Bikini', 'au Bikini', 'du Bikini'];
        yield 'lower-case article' => ['le Bikini', 'au Bikini', 'du Bikini'];
        yield 'upper-case article' => ['LES ABATTOIRS', 'aux ABATTOIRS', 'des ABATTOIRS'];
        yield '"La" never contracts' => ['La Rochelle', 'à La Rochelle', 'de La Rochelle'];
        yield '"L\'" never contracts' => ["L'Isle-Jourdain", "à L'Isle-Jourdain", "de L'Isle-Jourdain"];
        yield 'typographic apostrophe' => ['L’Usine', 'à L’Usine', 'de L’Usine'];
        yield 'hyphenated article' => ["Les Sables-d'Olonne", "aux Sables-d'Olonne", "des Sables-d'Olonne"];
        yield 'word merely starting with "Les"' => ['Lesparre-Médoc', 'à Lesparre-Médoc', 'de Lesparre-Médoc'];
        yield 'word merely starting with "Le"' => ['Lens', 'à Lens', 'de Lens'];
        yield 'vowel' => ['Angers', 'à Angers', "d'Angers"];
        yield 'accented vowel' => ['Évry', 'à Évry', "d'Évry"];
        yield 'ligature' => ['Œuilly', 'à Œuilly', "d'Œuilly"];
        yield '"Y" before a consonant' => ['Yvetot', 'à Yvetot', "d'Yvetot"];
        yield '"Y" before a vowel' => ['Yutz', 'à Yutz', 'de Yutz'];
        yield '"H" is never elided' => ['Hendaye', 'à Hendaye', 'de Hendaye'];
        yield 'digit' => ['3 Baudets', 'à 3 Baudets', 'de 3 Baudets'];
        yield 'surrounding spaces' => ['  Albi ', 'à Albi', "d'Albi"];
    }
}
