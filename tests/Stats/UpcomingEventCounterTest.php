<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Stats\UpcomingEventCounter;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class UpcomingEventCounterTest extends AppKernelTestCase
{
    use CountsUpcomingEvents;

    private UpcomingEventCounter $counter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->counter = self::counter();
    }

    public function testOnlyThePublishedEventsToComeAreCounted(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $france]);
        $empty = CityFactory::createOne(['name' => 'Empty', 'country' => $france]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $alsoInToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $inLyon = PlaceFactory::createOne(['city' => $lyon, 'country' => $france]);
        $published = EventFactory::new()->withDates($tomorrow)->many(2)->create(['place' => $inToulouse])[0];
        EventFactory::new()->withDates($tomorrow)->create(['place' => $alsoInToulouse]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'draft' => true]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'duplicateOf' => $published]);
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['place' => $inLyon]);
        // Already started, not over yet
        EventFactory::new()->withDates(new DateTimeImmutable('-3 days'), $tomorrow)->create(['place' => $inLyon]);

        $this->counter->refresh();

        self::assertSame([2, 1, 2], array_map(static fn ($place): int => refresh($place)->getUpcomingEvents(), [$inToulouse, $alsoInToulouse, $inLyon]));
        self::assertSame([3, 2, 0], array_map(static fn ($city): int => refresh($city)->getUpcomingEvents(), [$toulouse, $lyon, $empty]));
        self::assertSame(5, refresh($france)->getUpcomingEvents());
    }

    public function testTheCountsGoBackToZeroWhenTheLastEventIsUnpublished(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $france = $toulouse->getCountry();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $france]);
        $event = EventFactory::new()->withDates(new DateTimeImmutable('tomorrow'))->create(['place' => $place]);
        $this->counter->refresh();
        self::assertSame(1, refresh($place)->getUpcomingEvents());

        $event->setDraft(true);
        save($event);
        $this->counter->refresh();

        self::assertSame(0, refresh($place)->getUpcomingEvents());
        self::assertSame(0, refresh($toulouse)->getUpcomingEvents());
        self::assertSame(0, refresh($france)->getUpcomingEvents());
    }

    public function testOnlyTheCountsThatChangedAreWritten(): void
    {
        $toulouse = CityFactory::toulouse()->create();
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $toulouse->getCountry()]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inToulouse = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $inLyon = PlaceFactory::createOne(['city' => $lyon, 'country' => $toulouse->getCountry()]);
        $concert = TagFactory::createOne(['name' => 'Concert']);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inToulouse, 'category' => $concert]);
        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'category' => $concert]);

        // The category counts are rewritten each time: Toulouse, Lyon and France
        self::assertSame(['countries' => 1, 'cities' => 2, 'places' => 2, 'categories' => 3], $this->counter->refresh());
        self::assertSame(['countries' => 0, 'cities' => 0, 'places' => 0, 'categories' => 3], $this->counter->refresh());

        EventFactory::new()->withDates($tomorrow)->create(['place' => $inLyon, 'category' => $concert]);

        self::assertSame(['countries' => 1, 'cities' => 1, 'places' => 1, 'categories' => 3], $this->counter->refresh());
        self::assertSame(2, refresh($inLyon)->getUpcomingEvents());
        self::assertSame(1, refresh($inToulouse)->getUpcomingEvents());
    }
}
