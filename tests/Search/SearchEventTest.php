<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Search;

use App\Enum\DateRangePreset;
use App\Search\DateRange;
use App\Search\SearchEvent;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SearchEventTest extends AppKernelTestCase
{
    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideRanges(): iterable
    {
        yield 'the first stop' => [5, 0];
        yield 'the default' => [25, 0];
        yield 'the last stop' => [SearchEvent::MAX_RANGE, 0];
        yield 'past the last stop' => [SearchEvent::MAX_RANGE + 1, 1];
        yield 'no radius' => [0, 1];
    }

    /**
     * The radius around a city goes up to the last stop of the agenda's slider, whatever the query string says.
     */
    #[DataProvider('provideRanges')]
    public function testTheRangeStaysWithinTheSlider(int $range, int $violations): void
    {
        $validator = self::getContainer()->get(ValidatorInterface::class);

        self::assertCount($violations, $validator->validatePropertyValue(SearchEvent::class, 'range', $range));
    }

    public function testWithoutAPeriodTheEventsStillToComeAreSearched(): void
    {
        $search = new SearchEvent();

        self::assertSame(DateRangePreset::Anytime, $search->getPreset());
        self::assertEquals(new DateRange(new DateTimeImmutable('today')), $search->getDateRange());
    }

    public function testAShortcutSearchesItsDays(): void
    {
        $search = new SearchEvent()->setWhen(DateRangePreset::ThisWeekend);

        self::assertSame(DateRangePreset::ThisWeekend, $search->getPreset());
        self::assertEquals(DateRangePreset::ThisWeekend->range(), $search->getDateRange());
    }

    /**
     * The agenda form sends its shortcut again with the dates picked in its date picker.
     */
    public function testTheDatesPickedWinOverTheShortcut(): void
    {
        $search = new SearchEvent()
            ->setWhen(DateRangePreset::ThisWeekend)
            ->setFrom(new DateTimeImmutable('2026-10-10'))
            ->setTo(new DateTimeImmutable('2026-10-12'));

        self::assertNull($search->getPreset());
        self::assertEquals(new DateRange(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-12')), $search->getDateRange());
    }
}
