<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SearchRepository;

use App\SearchRepository\EventNameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The pairs come from the measures on the events members published (EventNameMatcher): a member's event, then an
 * event of the same dates around it.
 */
final class EventNameMatcherTest extends TestCase
{
    /**
     * @param list<string> $context
     */
    #[DataProvider('provideTwins')]
    public function testTheSameEventWordedOtherwiseIsFound(string $name, string $other, array $context, bool $sameVenue): void
    {
        self::assertTrue(new EventNameMatcher()->isLikelySame($name, $other, $context, $sameVenue));
    }

    /**
     * @return iterable<string, array{string, string, list<string>, bool}>
     */
    public static function provideTwins(): iterable
    {
        yield 'case and accents' => ['Démente', 'DÉMENTE', ['Théâtre De La Violette', 'Toulouse'], true];
        yield 'elided article' => ["L'Ascenseur", "L'ASCENSEUR", ['Théâtre De La Violette', 'Toulouse'], true];
        yield 'the source keeps the title, the member adds what it is' => ['Musicophotographie – Projection photographique 2026', 'Musicophotographie', ['Arsenal', 'Metz', "L'Arsenal, Salle De L'Esplanade", 'Metz'], true];
        yield 'half the words at the same venue' => ['Les Cachottiers, une comédie de Luc CHAUMAR', 'Les cachottiers', ['Théâtre Le Bout', 'Paris'], true];
        yield 'the venue and the city in the title' => ["Concert d'Orelsan au Zénith", 'ORELSAN', ['Zénith', 'Toulouse', 'Zénith Toulouse Métropole', 'Toulouse'], false];
        yield 'a venue placed at the centre of its city' => ["La guerre des rides au festival d'Avignon 2026", 'La Guerre des Rides', ['Théâtre Laurette', 'Avignon', 'Avignon', 'Avignon'], false];
        yield 'a typo' => ['Krismas Kino - Part One - Animal Trainer Live & Solvane', 'Prismas Kino - Part One - Animal Trainer Live & Solvane', [], false];
        yield 'nothing distinctive, the same wording at the same venue' => ['Marché de Noël', 'MARCHÉ DE NOËL', ['Place du Capitole', 'Toulouse'], true];
    }

    /**
     * @param list<string> $context
     */
    #[DataProvider('provideStrangers')]
    public function testAnotherEventSharingWordsIsNot(string $name, string $other, array $context, bool $sameVenue): void
    {
        self::assertFalse(new EventNameMatcher()->isLikelySame($name, $other, $context, $sameVenue));
    }

    /**
     * @return iterable<string, array{string, string, list<string>, bool}>
     */
    public static function provideStrangers(): iterable
    {
        yield 'one of the events of a festival, a few kilometres off' => ['Festival Locombia : Nkumba System à la Salle Nougaro', 'Festival Locombia', ['Salle Nougaro', 'Toulouse', 'Le Metronum', 'Toulouse'], false];
        yield 'one word in common, far' => ['Partage', 'Veillée Partage Espérance', [], false];
        yield 'words any event may hold' => ['Ramène ta boule #2 - Marché de Noël Arty', 'Marché de Noël', [], false];
        yield 'the same generic wording elsewhere' => ['Marché de Noël', 'Marché de Noël', [], false];
        yield 'the venue repeated in every title' => ['NIGHT LIFE #7 - GURU CLUB', 'RDS PARTY - GURU CLUB', ['Guru Club', 'Toulouse'], true];
        yield 'the city and the year in common' => ['Peacock Society 2025', 'Appel à initiatives « Proches aidants de personnes âgées 2025 »', ['Paris'], false];
        yield 'one word in common, at the same venue' => ['Jeu de piste : "Le voleur du Grand Théâtre"', "SUR LA PISTE DU MOUSTIQUE TIGRE, L'EXPOZZZITION !", [], true];
    }
}
