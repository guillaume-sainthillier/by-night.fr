<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import\CrossSource;

use App\Import\CrossSource\EventTitleComparator;
use App\Import\CrossSource\MatchVerdict;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Titles of the same venue and day, as the import sources wrote them (dev data of 2026-09-22).
 */
final class EventTitleComparatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, MatchVerdict, 3?: list<string>}>
     */
    public static function provideTitles(): iterable
    {
        // The same show, however the sources write it
        yield 'case and accents' => ['Fabrice éboué', 'FABRICE ÉBOUÉ', MatchVerdict::Same];
        yield 'the tour' => ['Claudio capeo', 'Claudio Capéo - Tournée', MatchVerdict::Same];
        yield 'the tour and its year' => ['Bernard lavilliers', 'Bernard Lavilliers - Tour 2027', MatchVerdict::Same];
        yield '"dans" the show' => ['Elie Semoun dans Cactus', 'Elie Semoun - Cactus - Tournée', MatchVerdict::Same];
        yield 'punctuation' => ['Moche, Moche... et Rigolote !', 'Moche moche et rigolote', MatchVerdict::Same];
        yield 'HTML entities' => ['Bigflo & Oli', 'BIGFLO &amp; OLI', MatchVerdict::Same];
        yield 'the venue and the town' => ['Changer l\'eau des fleurs', 'Changer l\'Eau des Fleurs - Théâtre des Salinières, Bordeaux', MatchVerdict::Same, ['Théâtre Des Salinières', 'Bordeaux']];
        yield 'the opening act' => ['Jontavious Willis', 'Jontavious Willis (USA) + 1ère partie', MatchVerdict::Contained];
        yield 'a date in the title' => ['Nous Etions une Armée', 'NOUS ÉTIONS UNE ARMÉE | Lieu Chéri - 04 décembre 2026', MatchVerdict::Same, ['Lieu Chéri', 'Paris']];
        yield 'both sold out' => ['Orelsan (complet)', 'Orelsan - Tournée', MatchVerdict::Same];

        // One title names the artist, the other adds the show
        yield 'the artist and the show' => ['Stephan eicher', 'Stephan Eicher - Poussière d’Or Tour', MatchVerdict::Contained];
        yield 'a two-word artist and the show' => ['ANNE ROUMANOFF', 'Anne Roumanoff - L\'Âge Libre - Tournée', MatchVerdict::Contained];
        yield 'a lone word long enough' => ['NINHO', 'Ninho - Quattro Tour', MatchVerdict::Contained];

        // Never the same show
        yield 'a lone short word' => ['Gims', 'Gims - Le Nord se Souvient', MatchVerdict::Different];
        yield 'two shows of one artist' => ['Chantal Ladesou - Iconique', 'Chantal Ladesou - Forever', MatchVerdict::Different];
        yield 'two shows' => ['Le Dîner de Cons', 'Le Prénom', MatchVerdict::Different];
        yield 'the shorter says too little of the longer' => ['Kev Adams', 'Kev Adams et Gad Elmaleh - Tout est Possible Rodage', MatchVerdict::Different];
        yield 'a visit of the exhibition' => ['Exposition Picasso', 'Visite guidée de l\'exposition Picasso', MatchVerdict::Different];
        yield 'a workshop around the show' => ['Le Petit Prince', 'Atelier Le Petit Prince', MatchVerdict::Different];
        yield 'the kids\' version' => ['Le Lac des Cygnes', 'Le Lac des Cygnes pour enfants', MatchVerdict::Different];
        yield 'a parking' => ['Mylène Farmer', 'Parking - Mylène Farmer', MatchVerdict::OtherProduct];
        yield 'a package' => ['Mask Singer', 'Package Mask Singer', MatchVerdict::OtherProduct];
        yield 'a dinner show' => ['Cirque Arlette Gruss', 'Cirque Arlette Gruss - Dîner-Spectacle', MatchVerdict::OtherProduct];
        yield 'a hospitality package' => ['Karol g', 'Prestation karol g', MatchVerdict::OtherProduct];
        yield 'a VIP upgrade' => ['Indochine', 'Indochine - Pass VIP', MatchVerdict::OtherProduct];
        yield 'a tribute' => ['Queen', 'Hommage à Queen', MatchVerdict::Tribute];
        yield 'a symphonic version' => ['Hans Zimmer', 'Hans Zimmer - Orchestre symphonique', MatchVerdict::Tribute];
        yield 'another day of the festival' => ['Hellfest - Jour 1', 'Hellfest - Jour 2', MatchVerdict::OtherNumber];
        yield 'a weekday of the festival' => ['Festival Rio Loco samedi', 'Festival Rio Loco dimanche', MatchVerdict::OtherNumber];
        yield 'another episode' => ['Sellig - Episode 6', 'Sellig - Episode 5', MatchVerdict::OtherNumber];
        yield 'a generic title' => ['Visite guidée', 'Visite guidée', MatchVerdict::Generic];
        yield 'a generic concert' => ['Concert de Noël', 'Spectacle de Noël', MatchVerdict::Generic];
        yield 'only the venue in common' => ['Open Mic de la Comédie de Grenoble', 'Comédie de Grenoble', MatchVerdict::Generic, ['Comédie De Grenoble', 'Grenoble']];
    }

    /**
     * @param list<string> $context
     */
    #[DataProvider('provideTitles')]
    public function testCompare(string $left, string $right, MatchVerdict $expected, array $context = []): void
    {
        $comparator = new EventTitleComparator();

        self::assertSame($expected, $comparator->compare($left, $right, $context));
        self::assertSame($expected, $comparator->compare($right, $left, $context), 'The comparison is symmetric');
    }
}
