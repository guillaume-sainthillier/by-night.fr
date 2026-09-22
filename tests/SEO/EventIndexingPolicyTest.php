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
use App\SEO\EventIndexingPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class EventIndexingPolicyTest extends TestCase
{
    private EventIndexingPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new EventIndexingPolicy(new MockClock('2026-09-22 10:00:00'), 30);
    }

    public function testSitemapSinceIsMidnightAGracePeriodAgo(): void
    {
        self::assertSame('2026-08-23 00:00:00', $this->policy->getSitemapSince()->format('Y-m-d H:i:s'));
    }

    #[DataProvider('provideEndDates')]
    public function testPublishedEventsStayIndexableWhateverTheirAge(string $endDate): void
    {
        $event = new Event()
            ->setStartDate(new DateTimeImmutable('2014-01-01'))
            ->setEndDate(new DateTimeImmutable($endDate));

        self::assertTrue($this->policy->isIndexable($event));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEndDates(): iterable
    {
        yield 'ends tomorrow' => ['2026-09-23'];
        yield 'ended last month' => ['2026-08-15'];
        yield 'ended a decade ago' => ['2014-10-25'];
    }

    public function testDraftsAndDuplicatesAreNeverIndexable(): void
    {
        $upcoming = new DateTimeImmutable('2026-10-01');

        $draft = new Event()->setStartDate($upcoming)->setEndDate($upcoming)->setDraft(true);
        self::assertFalse($this->policy->isIndexable($draft));

        $duplicate = new Event()->setStartDate($upcoming)->setEndDate($upcoming)->setDuplicateOf(new Event());
        self::assertFalse($this->policy->isIndexable($duplicate));
    }

    public function testHasEndedComparesTheEndDateWithToday(): void
    {
        $endedYesterday = new Event()->setStartDate(new DateTimeImmutable('2026-09-20'))->setEndDate(new DateTimeImmutable('2026-09-21'));
        $endsToday = new Event()->setStartDate(new DateTimeImmutable('2026-09-22'))->setEndDate(new DateTimeImmutable('2026-09-22'));
        $endsTomorrow = new Event()->setStartDate(new DateTimeImmutable('2026-09-22'))->setEndDate(new DateTimeImmutable('2026-09-23'));

        self::assertTrue($this->policy->hasEnded($endedYesterday));
        self::assertFalse($this->policy->hasEnded($endsToday));
        self::assertFalse($this->policy->hasEnded($endsTomorrow));
    }

    public function testAnEventWithoutEndDateFallsBackOnItsStartDate(): void
    {
        $event = new Event()->setStartDate(new DateTimeImmutable('2026-06-01'));

        self::assertTrue($this->policy->hasEnded($event));
    }
}
