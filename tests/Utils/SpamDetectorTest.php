<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Utils\SpamDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpamDetectorTest extends TestCase
{
    #[DataProvider('provideSpam')]
    public function testSpamIsCaughtInEventsAndComments(string $text): void
    {
        self::assertTrue(SpamDetector::isSpam($text));
        self::assertTrue(SpamDetector::isSpamComment($text));
    }

    #[DataProvider('provideCommentSpam')]
    public function testCommentScamsAreCaughtInCommentsOnly(string $text): void
    {
        self::assertTrue(SpamDetector::isSpamComment($text));
        self::assertFalse(SpamDetector::isSpam($text));
    }

    public function testAnEventMayTalkAboutLoansAndListItsContacts(): void
    {
        self::assertFalse(SpamDetector::isSpam('Salon : obtenir son crédit immobilier, contact : adil@example.com, 05 61 00 00 00'));
    }

    #[DataProvider('provideGenuine')]
    public function testGenuineContentPasses(string $text): void
    {
        self::assertFalse(SpamDetector::isSpam($text));
        self::assertFalse(SpamDetector::isSpamComment($text));
    }

    /**
     * Event names the sources (OpenAgenda, Facebook) and members published, in all their spellings.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideSpam(): iterable
    {
        yield 'accented' => ['Offre de prêt entre particulier serieux et honnête'];
        yield 'upper case, no accent' => ['OFFRE DE PRET ENTRE PARTICULIER : RAPIDE ET SECURISE,'];
        yield 'bare offer' => ['OFFRE DE PRET'];
        yield 'plural' => ['PRÊT ENTRE PARTICULIERS SÉRIEUX ET HONNÊTE'];
        yield 'octroi' => ['Octroi de prêts entre particuliers en moins 72H'];
        yield 'financement' => ['Aide de financement entre particuliers sérieux'];
        yield "prêt d'argent" => ["Prets d'argent entre particulier rapide à 2%"];
        yield 'curly apostrophe' => ['Prêts d’argent entre particuliers'];
        yield 'testimony' => ["Témoignage d'une offre de prêt reçue en ce jour sans aucun frais"];
        yield 'lender' => ['Le seul prêteur français réel et honnête'];
        yield 'spread over lines' => ["Offre de\n  prêt rapide"];
        yield 'love spell' => ['RETOUR AFFECTIF AMOUREUX IMMÉDIAT'];
        yield 'love spell, curly apostrophe' => ['Retour de l’être aimé en 24h'];
    }

    /**
     * Comments posted under events: an e-mail address, a phone number or WhatsApp turns a loan or a medium into a scam.
     *
     * @return iterable<string, array{string}>
     */
    public static function provideCommentSpam(): iterable
    {
        yield 'looking for a loan' => ["VOUS ÊTES A LA RECHERCHE D'UN PRÊT DE TOUTE URGENCE ?"];
        yield 'credit and e-mail' => ['Je viens de trouver un crédit de 15.000€ en ligne. Contact: groupe.credit@example.com'];
        yield 'loan and spaced e-mail' => ["J'ai pu sauver ma maison grâce à son prêt, voici son mail : jean.dupont @example.com"];
        yield 'German credit' => ['Schnelles Kreditangebot, E-Mail: kredit@example.com'];
        yield 'medium and phone' => ['Médium Papa X vous garantit l’efficacité tél : +229 00 00 00 00'];
        yield 'marabouts and WhatsApp' => ["J'ai demandé à d'autres marabouts, contactez-le par WhatsApp"];
        yield 'mighty master' => ["Je veux témoigner de l'efficacité d'un puissant maître"];
        yield 'illuminati' => ["REJOIGNEZ LA FRATERNITÉ ILLUMINATIE AUJOURD'HUI"];
        yield 'baby adoption' => ['Adoptez un bébé ou enfant en 48 heures au plus'];
        yield 'lottery magic' => ['MAGIE POUR GAGNER AU LOTO ET A L’EUROMILLIONS'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideGenuine(): iterable
    {
        yield 'spring offer' => ['Offre de printemps !'];
        yield 'lending gear' => ['Atelier : prêt de matériel entre voisins'];
        yield 'lending cards' => ["C'est gratuit la première fois, prêt de cartes possible en nous en informant au préalable."];
        yield 'book swap' => ['Bourse aux livres : prêts et échanges entre particuliers'];
        yield 'ticket request' => ['Je suis intéressée pour avoir 2 places à 10 euros, jeanne@example.com'];
        yield 'baby question' => ["Bonjour, c'est possible visiter avec bébé d'un an ? Merci"];
        yield 'funded workshop' => ["L'atelier est financé à 100% par le Salon"];
    }
}
