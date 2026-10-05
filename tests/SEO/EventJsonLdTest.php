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
use App\Enum\EventStatus;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Parser\Common\FnacSpectaclesAwinParser;
use App\SEO\EventJsonLd;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class EventJsonLdTest extends AppKernelTestCase
{
    public function testTheDatesAreDays(): void
    {
        $event = $this->createEvent(['startDate' => new DateTimeImmutable('2026-09-22'), 'endDate' => new DateTimeImmutable('2026-09-24')]);

        $schema = $this->schema($event);

        self::assertSame('2026-09-22', $schema['startDate']);
        self::assertSame('2026-09-24', $schema['endDate']);
    }

    public function testTheDescriptionIsPlainText(): void
    {
        $event = $this->createEvent(['description' => '<p>Rock &amp; folk</p><p>Second&nbsp;set</p>']);

        self::assertSame("Rock & folk Second\u{a0}set", $this->schema($event)['description']);
    }

    public function testAKnownPriceIsAnOfferInEuros(): void
    {
        $event = $this->createEvent(['prices' => 'De 15€ à 25€']);

        $schema = $this->schema($event);

        self::assertSame(15, (int) $schema['offers']['price']);
        self::assertSame('EUR', $schema['offers']['priceCurrency']);
        self::assertSame('https://schema.org/InStock', $schema['offers']['availability']);
        self::assertSame($schema['url'], $schema['offers']['url']);
        self::assertArrayNotHasKey('isAccessibleForFree', $schema);
    }

    public function testTheOfferOfAnAffiliateEventLeadsToItsTicketing(): void
    {
        $event = $this->createEvent([
            'prices' => '22€',
            'fromData' => FnacSpectaclesAwinParser::getParserName(),
            'source' => 'https://www.awin1.com/pclick.php?p=1',
        ]);

        self::assertSame('https://www.awin1.com/pclick.php?p=1', $this->schema($event)['offers']['url']);
    }

    public function testTheAffiliateLinkWinsOverAnyOtherTicketing(): void
    {
        $event = $this->createEvent([
            'prices' => '22€',
            'fromData' => FnacSpectaclesAwinParser::getParserName(),
            'source' => 'https://www.awin1.com/pclick.php?p=1',
            'ticketUrl' => 'https://billetterie.example.org/concert',
        ]);

        self::assertSame('https://www.awin1.com/pclick.php?p=1', $this->schema($event)['offers']['url']);
    }

    public function testTheOfferLeadsToTheTicketingTheSourceGives(): void
    {
        $event = $this->createEvent(['prices' => '12€', 'ticketUrl' => 'https://billetterie.example.org/concert']);

        self::assertSame('https://billetterie.example.org/concert', $this->schema($event)['offers']['url']);
    }

    public function testAFreeEventIsAccessibleForFree(): void
    {
        $event = $this->createEvent(['prices' => 'Gratuit']);

        $schema = $this->schema($event);

        self::assertSame(0, (int) $schema['offers']['price']);
        self::assertTrue($schema['isAccessibleForFree']);
    }

    public function testAnUnknownPriceIsNoOffer(): void
    {
        $schema = $this->schema($this->createEvent(['prices' => null]));

        self::assertArrayNotHasKey('offers', $schema);
        self::assertArrayNotHasKey('isAccessibleForFree', $schema);
    }

    public function testASoldOutEventSaysSoEvenWithoutAPrice(): void
    {
        $schema = $this->schema($this->createEvent(['prices' => null, 'status' => EventStatus::SoldOut]));

        self::assertSame('https://schema.org/EventScheduled', $schema['eventStatus']);
        self::assertSame('https://schema.org/SoldOut', $schema['offers']['availability']);
        self::assertArrayNotHasKey('price', $schema['offers']);
    }

    public function testACancelledEventSellsNoTickets(): void
    {
        $schema = $this->schema($this->createEvent(['prices' => '10€', 'status' => EventStatus::Cancelled]));

        self::assertSame('https://schema.org/EventCancelled', $schema['eventStatus']);
        self::assertArrayNotHasKey('offers', $schema);
    }

    public function testTheArtistsAreThePerformers(): void
    {
        $schema = $this->schema($this->createEvent(['performers' => ['Seth', 'The Great Old Ones']]));

        self::assertSame([
            ['@type' => 'PerformingGroup', 'name' => 'Seth'],
            ['@type' => 'PerformingGroup', 'name' => 'The Great Old Ones'],
        ], $schema['performer']);
    }

    public function testAnEventWithoutArtistsHasNoPerformer(): void
    {
        self::assertArrayNotHasKey('performer', $this->schema($this->createEvent([])));
    }

    /**
     * @param list<string> $agendaTypes
     */
    #[DataProvider('provideSubtypes')]
    public function testTheSubtypeComesFromTheAgendaTypesThatNameAKindOfOuting(array $agendaTypes, string $expected): void
    {
        self::assertSame($expected, $this->schema($this->createEvent(['agendaTypes' => $agendaTypes]))['@type']);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideSubtypes(): iterable
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
     * @param array<string, mixed> $attributes
     */
    private function createEvent(array $attributes): Event
    {
        $city = CityFactory::toulouse()->create();

        return EventFactory::createOne([
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(Event $event): array
    {
        return json_decode(self::getContainer()->get(EventJsonLd::class)->generateEventJsonLd($event), true, 512, \JSON_THROW_ON_ERROR);
    }
}
