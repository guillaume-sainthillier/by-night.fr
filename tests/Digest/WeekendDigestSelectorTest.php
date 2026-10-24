<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Digest;

use App\Entity\Event;
use App\Enum\EventStatus;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Search\DateRange;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

/**
 * The events of a weekend selection, once Elasticsearch found those of the weekend in the city and around.
 */
final class WeekendDigestSelectorTest extends AppKernelTestCase
{
    use StubsWeekendSearch;

    public function testThePicturesComeFirstThenTheMostFollowedThenTheAgendaOrder(): void
    {
        $plain = $this->event('No picture, followed', ['participations' => 90]);
        $first = $this->event('Picture, first in the agenda', ['image' => $this->picture()]);
        $followed = $this->event('Picture, followed', ['participations' => 12, 'image' => $this->picture()]);
        $fetched = $this->event('Fetched picture', ['imageSystem' => $this->picture()]);

        $selection = $this->weekendSelector([$plain, $first, $fetched, $followed])->select(CityFactory::toulouse()->create(), $this->weekend());

        self::assertSame(
            ['Picture, followed', 'Picture, first in the agenda', 'Fetched picture', 'No picture, followed'],
            array_map(static fn (Event $event): ?string => $event->getName(), $selection),
        );
    }

    public function testTheSelectionStopsAtItsLimitAndLeavesOutWhatTheIndexStillHolds(): void
    {
        $events = [
            $this->event('Draft', ['draft' => true, 'image' => $this->picture()]),
            $this->event('One'),
            $this->event('Two'),
            $this->event('Three'),
        ];

        $selection = $this->weekendSelector($events)->select(CityFactory::toulouse()->create(), $this->weekend(), 2);

        self::assertSame(['One', 'Two'], array_map(static fn (Event $event): ?string => $event->getName(), $selection));
    }

    public function testAnEventThatMayNotTakePlaceAsListedIsLeftOut(): void
    {
        $events = [$this->event('Plain'), $this->event('Sold out', ['status' => EventStatus::SoldOut])];
        foreach ([EventStatus::Cancelled, EventStatus::Postponed, EventStatus::Rescheduled, EventStatus::MovedOnline] as $status) {
            // The most followed, with a picture: first if it were kept
            $events[] = $this->event($status->getLabel(), ['status' => $status, 'participations' => 50, 'image' => $this->picture()]);
        }

        $selection = $this->weekendSelector($events)->select(CityFactory::toulouse()->create(), $this->weekend());

        self::assertSame(['Plain', 'Sold out'], array_map(static fn (Event $event): ?string => $event->getName(), $selection));
    }

    public function testNothingFoundIsAnEmptySelection(): void
    {
        self::assertSame([], $this->weekendSelector([])->select(CityFactory::toulouse()->create(), $this->weekend()));
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function event(string $name, array $attributes = []): Event
    {
        return EventFactory::new()->withDates(new DateTimeImmutable('2026-10-24'))->create(['name' => $name] + $attributes);
    }

    private function picture(): EmbeddedFile
    {
        $picture = new EmbeddedFile();
        $picture->setName('poster.jpg');

        return $picture;
    }

    private function weekend(): DateRange
    {
        return new DateRange(new DateTimeImmutable('2026-10-23'), new DateTimeImmutable('2026-10-25'));
    }
}
