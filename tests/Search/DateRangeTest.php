<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Search;

use App\Search\DateRange;
use DateTime;
use DateTimeImmutable;
use Locale;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr');
    }

    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    /**
     * A period is made of whole days: the time of "now" or of a DateType field does not tell two periods apart.
     */
    public function testTheTimeOfTheDaysIsLeftOut(): void
    {
        $range = new DateRange(new DateTime('2026-10-10 21:30'), new DateTimeImmutable('2026-10-12 08:00'));

        self::assertEquals(new DateRange(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-12')), $range);
    }

    public function testTheLabelSaysTheDaysAsTheDatePickerDoes(): void
    {
        self::assertSame('Le 10 oct. 2026', new DateRange(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-10'))->label());
        self::assertSame('Du 10 oct. 2026 au 12 oct. 2026', new DateRange(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-12'))->label());
        self::assertSame('À partir du 10 oct. 2026', new DateRange(new DateTimeImmutable('2026-10-10'))->label());
    }

    public function testTheQueryStringHasNoEndForAnOpenPeriod(): void
    {
        self::assertSame(['from' => '2026-10-10', 'to' => '2026-10-12'], new DateRange(new DateTimeImmutable('2026-10-10'), new DateTimeImmutable('2026-10-12'))->toQuery());
        self::assertSame(['from' => '2026-10-10'], new DateRange(new DateTimeImmutable('2026-10-10'))->toQuery());
    }
}
