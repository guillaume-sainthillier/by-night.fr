<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SEO;

use App\Entity\Event;
use App\SEO\EventSchemaType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The kinds and agenda types below are those of production events (2026-10-05).
 */
final class EventSchemaTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function provideKinds(): iterable
    {
        yield 'no kind' => [null, 'Event'];
        yield 'a SeeTickets concert' => ['Concert', 'MusicEvent'];
        yield 'a SeeTickets play' => ['Théâtre', 'TheaterEvent'];
        yield 'a SeeTickets festival' => ['Festival', 'Festival'];
        yield 'a SeeTickets show (stand-up, magic, musicals)' => ['Spectacle', 'Event'];
        yield 'a SowProg club night' => ['Clubbing', 'MusicEvent'];
        yield 'a SowProg dance show' => ['Danse', 'DanceEvent'];
        yield 'a SowProg show for children' => ['Jeune Public', 'ChildrensEvent'];
        yield 'a DATAtourisme concert, whatever the order and the noise' => ['Culture, Musique, Concert, Spectacle', 'MusicEvent'];
        yield 'a DATAtourisme play' => ['Théâtre, Spectacle, Culture', 'TheaterEvent'];
        yield 'a DATAtourisme exhibition' => ['Exposition, Culture', 'ExhibitionEvent'];
        yield 'a DATAtourisme competition' => ['Sport, Compétition', 'SportsEvent'];
        yield 'a DATAtourisme flea market' => ['Brocante, Commerce', 'SaleEvent'];
        yield 'DATAtourisme sport, on a mushroom walk as on a play' => ['Sport', 'Event'];
        yield 'DATAtourisme commerce, a market or a winery open day' => ['Commerce', 'Event'];
        yield 'DATAtourisme community and culture, on anything' => ['Communautaire, Culture, Famille', 'Event'];
        yield 'two kinds, a mixed bill' => ['Concert, Musique, Commerce, Brocante', 'Event'];
        yield 'OpenAgenda keywords naming one kind' => ['Yael Naim,Concert,Canteleu,Pop,Electro', 'MusicEvent'];
        yield 'an OpenAgenda vide-grenier' => ['vide grenier,brocante,reemploi', 'SaleEvent'];
        yield 'an OpenAgenda theatre workshop' => ['atelier,théâtre,jeu,impro,scène', 'Event'];
        yield 'an OpenAgenda dance workshop' => ['Ateliers,danse,cultures urbaines', 'Event'];
    }

    #[DataProvider('provideKinds')]
    public function testTheKindTheSourceGivesNamesTheSubtype(?string $kind, string $expected): void
    {
        self::assertSame($expected, $this->resolve($kind, []));
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideAgendaTypes(): iterable
    {
        yield 'no type' => [[], 'Event'];
        yield 'a concert' => [['concert'], 'MusicEvent'];
        yield 'a family concert' => [['concert', 'family'], 'MusicEvent'];
        yield 'an exhibition' => [['exhibition'], 'ExhibitionEvent'];
        yield 'an exhibition also read as a show' => [['show', 'exhibition'], 'ExhibitionEvent'];
        yield 'an exhibition that is also a concert' => [['concert', 'show', 'exhibition'], 'Event'];
        yield 'a musical show' => [['concert', 'show'], 'Event'];
        yield 'a show' => [['show'], 'Event'];
        yield 'a family outing' => [['family'], 'Event'];
        yield 'a type no longer known' => [['cinema'], 'Event'];
    }

    /**
     * @param list<string> $agendaTypes
     */
    #[DataProvider('provideAgendaTypes')]
    public function testWithoutAKindTheAgendaTypesNameTheSubtype(array $agendaTypes, string $expected): void
    {
        self::assertSame($expected, $this->resolve(null, $agendaTypes));
    }

    public function testTheKindOfTheSourceWinsOverTheAgendaTypes(): void
    {
        self::assertSame('TheaterEvent', $this->resolve('Théâtre', ['concert']));
    }

    public function testTwoKindsLeaveItToTheAgendaTypes(): void
    {
        self::assertSame('ExhibitionEvent', $this->resolve('Théâtre, Exposition', ['exhibition']));
    }

    /**
     * @param list<string> $agendaTypes
     */
    private function resolve(?string $kind, array $agendaTypes): string
    {
        return new EventSchemaType()->resolve(new Event()->setType($kind)->setAgendaTypes($agendaTypes));
    }
}
