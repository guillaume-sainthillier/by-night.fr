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
use App\SEO\EventSEO;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

final class EventSEOTest extends AppKernelTestCase
{
    public function testASingleDayEventIsDescribedByItsDay(): void
    {
        // Distinct objects, as Doctrine hydrates them
        $event = new Event()
            ->setStartDate(new DateTimeImmutable('2026-09-22'))
            ->setEndDate(new DateTimeImmutable('2026-09-22'));

        self::assertStringStartsWith('le ', $this->getSeo()->getEventDate($event));
    }

    public function testAnEventOverSeveralDaysIsDescribedByItsRange(): void
    {
        $event = new Event()
            ->setStartDate(new DateTimeImmutable('2026-09-22'))
            ->setEndDate(new DateTimeImmutable('2026-09-24'));

        self::assertStringStartsWith('du ', $this->getSeo()->getEventDate($event));
    }

    private function getSeo(): EventSEO
    {
        return self::getContainer()->get(EventSEO::class);
    }
}
