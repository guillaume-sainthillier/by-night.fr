<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Ticketing;

use App\Entity\Event;
use App\Enum\EventStatus;
use App\Factory\EventFactory;
use App\Parser\Common\CDiscountAwinParser;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\Parser\Common\OpenAgendaParser;
use App\Parser\Common\SeeTicketsKwankoParser;
use App\Tests\AppKernelTestCase;
use App\Ticketing\EventTicketOffers;
use App\Ticketing\TicketOffer;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class EventTicketOffersTest extends AppKernelTestCase
{
    public function testATicketingSiteIsBookedThroughOurAffiliateLink(): void
    {
        $event = $this->event(FnacSpectaclesAwinParser::getParserName(), '25€', 'https://www.awin1.com/fnac');

        $offers = $this->offers($event);

        self::assertCount(1, $offers);
        self::assertSame('Fnac Spectacles', $offers[0]->seller);
        self::assertSame('https://www.awin1.com/fnac', $offers[0]->url);
        self::assertTrue($offers[0]->affiliate);
        self::assertSame(25.0, $offers[0]->startingPrice);
        self::assertSame("25\u{a0}€", $offers[0]->priceLabel['label'] ?? null);
    }

    public function testTheOrganizersBookingIsNamedByItsSite(): void
    {
        $event = $this->event(OpenAgendaParser::getParserName(), '12€', ticketUrl: 'www.billetterie.example.org/concert');

        $offers = $this->offers($event);

        self::assertCount(1, $offers);
        self::assertSame('billetterie.example.org', $offers[0]->seller);
        self::assertSame('https://www.billetterie.example.org/concert', $offers[0]->url);
        self::assertFalse($offers[0]->affiliate);
    }

    public function testAnEventWithoutBookingHasNoOffer(): void
    {
        self::assertSame([], $this->offers($this->event(OpenAgendaParser::getParserName(), '12€')));
    }

    public function testEverySiteSellingTheShowIsOfferedCheapestFirst(): void
    {
        $canonical = $this->event(FnacSpectaclesAwinParser::getParserName(), '25€', 'https://www.awin1.com/fnac');
        $this->duplicateOf($canonical, CDiscountAwinParser::getParserName(), '22€', 'https://www.awin1.com/cdiscount');
        $this->duplicateOf($canonical, SeeTicketsKwankoParser::getParserName(), '19€', 'https://kwanko.com/seetickets', EventStatus::SoldOut);
        $this->duplicateOf($canonical, SeeTicketsKwankoParser::getParserName(), null, 'https://kwanko.com/seetickets/2');

        self::assertSame([
            ['CDiscount', 22.0, false],
            ['Fnac Spectacles', 25.0, false],
            // Its row still on sale wins over its cheaper sold-out one, last for its unknown price
            ['SeeTickets', null, false],
        ], $this->summary($this->offers($canonical)));
    }

    public function testOneOfferPerSiteAtItsLowestPrice(): void
    {
        $canonical = $this->event(FnacSpectaclesAwinParser::getParserName(), '25€', 'https://www.awin1.com/fnac/1');
        $this->duplicateOf($canonical, FnacSpectaclesAwinParser::getParserName(), '20€', 'https://www.awin1.com/fnac/2');

        $offers = $this->offers($canonical);

        self::assertCount(1, $offers);
        self::assertSame('https://www.awin1.com/fnac/2', $offers[0]->url);
    }

    public function testASiteStillSellingTheShowBeatsASoldOutOne(): void
    {
        $canonical = $this->event(FnacSpectaclesAwinParser::getParserName(), '15€', 'https://www.awin1.com/fnac', EventStatus::SoldOut);
        $this->duplicateOf($canonical, CDiscountAwinParser::getParserName(), '40€', 'https://www.awin1.com/cdiscount');

        self::assertSame([['CDiscount', 40.0, false], ['Fnac Spectacles', 15.0, true]], $this->summary($this->offers($canonical)));
    }

    public function testTheOrganizersBookingStepsAsideForATicketingSite(): void
    {
        $canonical = $this->event(OpenAgendaParser::getParserName(), '12€', ticketUrl: 'https://billetterie.example.org/concert');
        $this->duplicateOf($canonical, FnacSpectaclesAwinParser::getParserName(), '25€', 'https://www.awin1.com/fnac');

        self::assertSame([['Fnac Spectacles', 25.0, false]], $this->summary($this->offers($canonical)));
    }

    public function testTheOrganizersBookingStaysWhenEveryTicketingSiteIsSoldOut(): void
    {
        $canonical = $this->event(OpenAgendaParser::getParserName(), '12€', ticketUrl: 'https://billetterie.example.org/concert');
        $this->duplicateOf($canonical, FnacSpectaclesAwinParser::getParserName(), '25€', 'https://www.awin1.com/fnac', EventStatus::SoldOut);

        self::assertSame([['billetterie.example.org', 12.0, false], ['Fnac Spectacles', 25.0, true]], $this->summary($this->offers($canonical)));
    }

    public function testACancelledOrRemovedRowSellsNothing(): void
    {
        $canonical = $this->event(FnacSpectaclesAwinParser::getParserName(), '25€', 'https://www.awin1.com/fnac');
        $this->duplicateOf($canonical, CDiscountAwinParser::getParserName(), '22€', 'https://www.awin1.com/cdiscount', EventStatus::Cancelled);
        $removed = $this->duplicateOf($canonical, SeeTicketsKwankoParser::getParserName(), '19€', 'https://kwanko.com/seetickets');
        $removed->markRemovedAtSource();
        save($removed);
        refresh($canonical);

        self::assertSame([['Fnac Spectacles', 25.0, false]], $this->summary($this->offers($canonical)));
    }

    private function event(string $source, ?string $prices, ?string $affiliateUrl = null, ?EventStatus $status = null, ?string $ticketUrl = null): Event
    {
        return EventFactory::createOne([
            'fromData' => $source,
            'prices' => $prices,
            'source' => $affiliateUrl,
            'ticketUrl' => $ticketUrl,
            'status' => $status,
        ]);
    }

    private function duplicateOf(Event $canonical, string $source, ?string $prices, string $affiliateUrl, ?EventStatus $status = null): Event
    {
        $duplicate = $this->event($source, $prices, $affiliateUrl, $status);
        $duplicate->setDuplicateOf($canonical);
        save($duplicate);
        refresh($canonical);

        return $duplicate;
    }

    /**
     * @return list<TicketOffer>
     */
    private function offers(Event $event): array
    {
        return self::getContainer()->get(EventTicketOffers::class)->forEvent($event);
    }

    /**
     * @param list<TicketOffer> $offers
     *
     * @return list<array{string, float|null, bool}>
     */
    private function summary(array $offers): array
    {
        return array_map(static fn (TicketOffer $offer): array => [$offer->seller, $offer->startingPrice, $offer->soldOut], $offers);
    }
}
