<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\SEO;

use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\SEO\EventJsonLd;
use App\Tests\AppKernelTestCase;
use DateTimeImmutable;

final class EventJsonLdTest extends AppKernelTestCase
{
    public function testTheDatesAreDays(): void
    {
        $city = CityFactory::toulouse()->create();
        $event = EventFactory::new()
            ->withDates(new DateTimeImmutable('2026-09-22'), new DateTimeImmutable('2026-09-24'))
            ->create(['place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()])]);

        $schema = json_decode(self::getContainer()->get(EventJsonLd::class)->generateEventJsonLd($event), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('2026-09-22', $schema['startDate']);
        self::assertSame('2026-09-24', $schema['endDate']);
    }
}
