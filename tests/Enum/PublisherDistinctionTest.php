<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Enum;

use App\Entity\Publisher;
use App\Enum\PublisherDistinction;
use App\Stats\PublisherCompletion;
use App\Stats\PublisherStats;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublisherDistinctionTest extends TestCase
{
    public function testANewPublisherHasNone(): void
    {
        self::assertSame([], $this->earned(new PublisherStats()));
    }

    public function testEachThresholdEarnsItsDistinction(): void
    {
        $stats = new PublisherStats(
            events: PublisherDistinction::PROLIFIC_EVENTS,
            upcomingEvents: 1,
            freeEvents: PublisherDistinction::FREE_ENTRY_EVENTS,
            participations: 20,
            interests: PublisherDistinction::CROWD_PULLER_AUDIENCE - 20,
            cities: PublisherDistinction::ON_TOUR_CITIES,
            busiestPlaceEvents: PublisherDistinction::RESIDENT_EVENTS,
            firstYear: 2026 - PublisherDistinction::PIONEER_YEARS,
            activeMonths: PublisherDistinction::REGULAR_MONTHS,
        );

        self::assertSame([
            PublisherDistinction::Prolific,
            PublisherDistinction::Regular,
            PublisherDistinction::CrowdPuller,
            PublisherDistinction::Resident,
            PublisherDistinction::OnTour,
            PublisherDistinction::FreeEntry,
            PublisherDistinction::Pioneer,
        ], $this->earned($stats));
    }

    public function testJustUnderTheThresholdsEarnsNothing(): void
    {
        $stats = new PublisherStats(
            events: PublisherDistinction::PROLIFIC_EVENTS - 1,
            freeEvents: PublisherDistinction::FREE_ENTRY_EVENTS - 1,
            participations: PublisherDistinction::CROWD_PULLER_AUDIENCE - 1,
            cities: PublisherDistinction::ON_TOUR_CITIES - 1,
            busiestPlaceEvents: PublisherDistinction::RESIDENT_EVENTS - 1,
            firstYear: 2026 - PublisherDistinction::PIONEER_YEARS + 1,
            activeMonths: PublisherDistinction::REGULAR_MONTHS - 1,
        );

        self::assertSame([], $this->earned($stats));
    }

    public function testACompletePageIsADistinction(): void
    {
        $publisher = new Publisher()->setDescription('Salle de concerts')->setWebsite('https://lebikini.com');
        $publisher->getLogo()->setName('logo.png');
        $publisher->getCover()->setName('cover.png');
        $stats = new PublisherStats(upcomingEvents: 1);

        self::assertSame([PublisherDistinction::CompletePage], PublisherDistinction::earnedBy($stats, PublisherCompletion::of($publisher, $stats)));
    }

    /**
     * @return list<PublisherDistinction>
     */
    private function earned(PublisherStats $stats): array
    {
        return PublisherDistinction::earnedBy($stats, PublisherCompletion::of(new Publisher(), $stats), new DateTimeImmutable('2026-10-23'));
    }
}
