<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Import\CrossSource;

use App\Entity\Event;
use App\Entity\Place;
use App\Entity\User;
use App\Enum\EventStatus;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\EventTimesheetFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Import\CrossSource\CrossSourceMatcher;
use App\Import\CrossSource\EventTitleComparator;
use App\Import\CrossSource\MatchVerdict;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use Override;

use function Zenstruck\Foundry\Persistence\refresh;
use function Zenstruck\Foundry\Persistence\save;

final class CrossSourceMatcherTest extends AppKernelTestCase
{
    private CrossSourceMatcher $matcher;

    private Place $venue;

    private User $user;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new CrossSourceMatcher(new EventTitleComparator());

        $country = CountryFactory::createOne(['id' => 'FR']);
        $city = CityFactory::createOne(['name' => 'Toulouse', 'country' => $country]);
        $this->venue = PlaceFactory::createOne(['name' => 'Zénith de Toulouse Métropole', 'city' => $city, 'country' => $country]);
        $this->user = UserFactory::createOne();
    }

    public function testTwoTicketingSitesSellingTheSameShowMatch(): void
    {
        $fnac = $this->event('Fnac Spectacles', 'Claudio Capéo - Tournée', '2026-12-04');
        $cdiscount = $this->event('CDiscount', 'Claudio capeo', '2026-12-04');

        self::assertSame(MatchVerdict::Same, $this->matcher->match($fnac, $cdiscount));
    }

    public function testTheVenueAndTheTownInTheTitleAreIgnored(): void
    {
        $fnac = $this->event('Fnac Spectacles', 'Claudio Capéo - Zénith de Toulouse', '2026-12-04');
        $cdiscount = $this->event('CDiscount', 'Claudio capeo', '2026-12-04');

        self::assertSame(MatchVerdict::Same, $this->matcher->match($fnac, $cdiscount));
    }

    public function testOneSourceIsLeftToTheFamilies(): void
    {
        $first = $this->event('Fnac Spectacles', 'Claudio Capéo', '2026-12-04');
        $second = $this->event('Fnac Spectacles', 'Claudio Capéo', '2026-12-04');

        self::assertSame(MatchVerdict::NotComparable, $this->matcher->match($first, $second));
    }

    public function testAMembersEventIsNeverMerged(): void
    {
        $member = $this->event(null, 'Claudio Capéo', '2026-12-04');
        $fnac = $this->event('Fnac Spectacles', 'Claudio Capéo', '2026-12-04');

        self::assertSame(MatchVerdict::NotComparable, $this->matcher->match($member, $fnac));
    }

    public function testAnEventItsSourceTookBackIsNotMatched(): void
    {
        $removed = $this->event('Open Agenda', 'Claudio Capéo', '2026-12-04');
        $removed->markRemovedAtSource();
        save($removed);
        $fnac = $this->event('Fnac Spectacles', 'Claudio Capéo', '2026-12-04');

        self::assertSame(EventStatus::Removed, $removed->getStatus());
        self::assertSame(MatchVerdict::NotComparable, $this->matcher->match($removed, $fnac));
    }

    public function testADuplicateIsItsCanonicalsBusiness(): void
    {
        $canonical = $this->event('Fnac Spectacles', 'Claudio Capéo', '2026-12-04');
        $duplicate = $this->event('Fnac Spectacles', 'Claudio Capéo', '2026-12-04');
        $duplicate->setDuplicateOf($canonical);
        save($duplicate);
        $cdiscount = $this->event('CDiscount', 'Claudio capeo', '2026-12-04');

        self::assertSame(MatchVerdict::NotComparable, $this->matcher->match($duplicate, $cdiscount));
    }

    public function testTwoVenuesKeepTheirShowsApart(): void
    {
        $fnac = $this->event('Fnac Spectacles', 'Claudio Capéo', '2026-12-04');
        $cdiscount = $this->event('CDiscount', 'Claudio capeo', '2026-12-04', PlaceFactory::createOne([
            'name' => 'Zénith de Pau',
            'city' => $this->venue->getCity(),
            'country' => $this->venue->getCountry(),
        ]));

        self::assertSame(MatchVerdict::OtherVenue, $this->matcher->match($fnac, $cdiscount));
    }

    public function testTwoVisitsOfATourToTheSameVenueAreTwoShows(): void
    {
        $spring = $this->event('Fnac Spectacles', 'Florent Pagny', '2026-04-05');
        $winter = $this->event('CDiscount', 'Florent pagny', '2026-12-14');

        self::assertSame(MatchVerdict::OtherDay, $this->matcher->match($spring, $winter));
    }

    public function testTheSessionsTellTheDaysNotTheRange(): void
    {
        // One row for both visits: its range spans the months between them
        $tour = $this->event('Fnac Spectacles', 'Florent Pagny', '2026-04-05', endDate: '2026-12-15');
        EventTimesheetFactory::new()->on('2026-04-05')->create(['event' => $tour]);
        EventTimesheetFactory::new()->on('2026-12-15')->create(['event' => $tour]);
        refresh($tour);

        self::assertSame(MatchVerdict::OtherDay, $this->matcher->match($tour, $this->event('CDiscount', 'Florent pagny', '2026-09-20')));
        self::assertSame(MatchVerdict::Same, $this->matcher->match($tour, $this->event('CDiscount', 'Florent pagny', '2026-12-15')));
    }

    public function testATributeOnTheSameNightIsAnotherShow(): void
    {
        $show = $this->event('CDiscount', 'Queen', '2026-12-04');
        $tribute = $this->event('Fnac Spectacles', 'Hommage à Queen', '2026-12-04');

        self::assertSame(MatchVerdict::Tribute, $this->matcher->match($show, $tribute));
    }

    private function event(?string $source, string $name, string $date, ?Place $place = null, ?string $endDate = null): Event
    {
        return EventFactory::createOne([
            'fromData' => $source,
            'name' => $name,
            'place' => $place ?? $this->venue,
            'placeCity' => 'Toulouse',
            'user' => $this->user,
            'startDate' => new DateTimeImmutable($date),
            'endDate' => new DateTimeImmutable($endDate ?? $date),
        ]);
    }
}
