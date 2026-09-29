<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Utils;

use App\Entity\Event;
use App\Utils\GoogleCalendarLink;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GoogleCalendarLinkTest extends TestCase
{
    private const string EVENT_URL = 'https://by-night.fr/rouen/soiree/le-gros-rouen--42';

    public function testAWeekendEndsTheDayAfterItsLastDay(): void
    {
        $parameters = $this->parameters($this->event('2026-09-26', '2026-09-27'));

        self::assertSame('TEMPLATE', $parameters['action']);
        self::assertSame('Le Gros Rouen', $parameters['text']);
        self::assertSame('20260926/20260928', $parameters['dates']);
        self::assertSame('Tous les détails sur By Night : ' . self::EVENT_URL, $parameters['details']);
    }

    public function testAOneDayEventWithoutAnEndDateTakesThatDay(): void
    {
        self::assertSame('20261231/20270101', $this->parameters($this->event('2026-12-31', null))['dates']);
    }

    public function testAnEndDateBeforeTheStartIsIgnored(): void
    {
        self::assertSame('20260926/20260927', $this->parameters($this->event('2026-09-26', '2026-09-20'))['dates']);
    }

    public function testTheLocationSkipsWhatTheVenueDoesNotHave(): void
    {
        $event = $this->event('2026-09-26', null)
            ->setPlaceName('Halle aux Toiles')
            ->setPlaceStreet('19 place de la Basse Vieille Tour')
            ->setPlacePostalCode('76000')
            ->setPlaceCity('Rouen');
        self::assertSame('Halle aux Toiles, 19 place de la Basse Vieille Tour, 76000 Rouen', $this->parameters($event)['location']);

        $event->setPlaceStreet(null)->setPlacePostalCode(null);
        self::assertSame('Halle aux Toiles, Rouen', $this->parameters($event)['location']);
    }

    public function testNoLinkWithoutADate(): void
    {
        self::assertNull(GoogleCalendarLink::forEvent($this->event(null, null), self::EVENT_URL));
    }

    private function event(?string $startDate, ?string $endDate): Event
    {
        return new Event()
            ->setName('Le Gros Rouen')
            ->setStartDate(null === $startDate ? null : new DateTimeImmutable($startDate))
            ->setEndDate(null === $endDate ? null : new DateTimeImmutable($endDate));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function parameters(Event $event): array
    {
        $url = GoogleCalendarLink::forEvent($event, self::EVENT_URL);
        self::assertNotNull($url);
        self::assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $parameters);

        return $parameters;
    }
}
