<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Stats;

use App\Entity\Publisher;
use App\Enum\PublisherPageStep;
use App\Stats\PublisherCompletion;
use App\Stats\PublisherStats;
use PHPUnit\Framework\TestCase;

final class PublisherCompletionTest extends TestCase
{
    public function testAnEmptyPageHasEverythingLeftToDo(): void
    {
        $completion = PublisherCompletion::of(new Publisher(), new PublisherStats());

        self::assertSame(0, $completion->getPercent());
        self::assertSame(PublisherPageStep::cases(), $completion->missing);
    }

    public function testEachStepCountsTheSame(): void
    {
        $publisher = new Publisher()->setSocialLinks(['instagram' => 'https://instagram.com/lebikini']);

        $completion = PublisherCompletion::of($publisher, new PublisherStats(upcomingEvents: 2));

        self::assertSame([PublisherPageStep::Links, PublisherPageStep::UpcomingEvent], $completion->done);
        self::assertSame(40, $completion->getPercent());
        self::assertFalse($completion->isComplete());
    }

    public function testAWebsiteAloneIsEnoughForTheLinks(): void
    {
        $completion = PublisherCompletion::of(new Publisher()->setWebsite('https://lebikini.com'), new PublisherStats());

        self::assertSame([PublisherPageStep::Links], $completion->done);
    }
}
