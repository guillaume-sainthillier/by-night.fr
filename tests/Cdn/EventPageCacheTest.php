<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Cdn;

use App\Cdn\EventPageCache;
use App\Entity\Event;
use App\SEO\EventIndexingPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Response;
use Vich\UploaderBundle\Entity\File as EmbeddedFile;

final class EventPageCacheTest extends TestCase
{
    private const string NOW = '2026-09-30 10:00:00';

    #[DataProvider('provideSharedMaxAges')]
    public function testSharedMaxAge(?string $endDate, int $expected): void
    {
        // A new Event starts today: only the dates set here count
        $event = new Event()->setStartDate(null)->setEndDate(null === $endDate ? null : new DateTimeImmutable($endDate));

        self::assertSame($expected, new EventPageCache(new MockClock(self::NOW))->sharedMaxAge($event));
    }

    /**
     * @return iterable<string, array{?string, int}>
     */
    public static function provideSharedMaxAges(): iterable
    {
        yield 'upcoming: a day' => ['2026-10-15', 86400];
        yield 'last day tomorrow: a day' => ['2026-10-01', 86400];
        yield 'last day today: until midnight, when it turns into an ended event' => ['2026-09-30', 14 * 3600];
        yield 'ended: a week' => ['2026-09-29', 604800];
        yield 'ended long ago: a week' => ['2014-10-25', 604800];
        yield 'no date: a day' => [null, 86400];
    }

    public function testFallsBackToTheStartDate(): void
    {
        $event = new Event()->setStartDate(new DateTimeImmutable('2026-09-01'))->setEndDate(null);

        self::assertSame(604800, new EventPageCache(new MockClock(self::NOW))->sharedMaxAge($event));
    }

    public function testAnUpcomingPageExpiresWhenTheIndexingPolicySaysTheEventHasEnded(): void
    {
        $event = new Event()->setStartDate(new DateTimeImmutable('2026-09-30'))->setEndDate(new DateTimeImmutable('2026-09-30'));
        $clock = new MockClock(self::NOW);
        $expiresAt = $clock->now()->modify(\sprintf('+%d seconds', new EventPageCache($clock)->sharedMaxAge($event)));

        self::assertFalse(new EventIndexingPolicy(new MockClock($expiresAt->modify('-1 second')), 30)->hasEnded($event));
        self::assertTrue(new EventIndexingPolicy(new MockClock($expiresAt), 30)->hasEnded($event));
    }

    public function testNeverCachesForLessThanAMinute(): void
    {
        $event = new Event()->setEndDate(new DateTimeImmutable('2026-09-30'));

        self::assertSame(60, new EventPageCache(new MockClock('2026-09-30 23:59:30'))->sharedMaxAge($event));
    }

    public function testAppliesTheLifetimeAndTheTags(): void
    {
        $response = new EventPageCache(new MockClock(self::NOW))->applyTo(new Response(), new Event()->setEndDate(new DateTimeImmutable('2026-09-29')));

        self::assertSame(604800, $response->getMaxAge());
        self::assertSame('event', $response->headers->get('Cache-Tag'));
    }

    public function testAPageShowingABorrowedPictureIsPurgedWithItsLender(): void
    {
        $picture = new EmbeddedFile();
        $picture->setName('cdiscount.jpg');
        $lender = new Event()->setImageSystem($picture);
        new ReflectionProperty(Event::class, 'id')->setValue($lender, 42);

        $tags = new EventPageCache(new MockClock(self::NOW))->tags(new Event()->setPictureFrom($lender));

        self::assertSame(['event', 'event-42'], $tags);
    }
}
