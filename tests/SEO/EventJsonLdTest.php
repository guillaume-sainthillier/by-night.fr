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
        $event = $this->createEvent(['startDate' => new DateTimeImmutable('2026-09-22'), 'endDate' => new DateTimeImmutable('2026-09-24')]);

        $schema = $this->schema($event);

        self::assertSame('2026-09-22', $schema['startDate']);
        self::assertSame('2026-09-24', $schema['endDate']);
    }

    public function testTheDescriptionIsPlainText(): void
    {
        $event = $this->createEvent(['description' => '<p>Rock &amp; folk</p><p>Second&nbsp;set</p>']);

        self::assertSame("Rock & folk Second\u{a0}set", $this->schema($event)['description']);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createEvent(array $attributes): Event
    {
        $city = CityFactory::toulouse()->create();

        return EventFactory::createOne([
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            ...$attributes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(Event $event): array
    {
        return json_decode(self::getContainer()->get(EventJsonLd::class)->generateEventJsonLd($event), true, 512, \JSON_THROW_ON_ERROR);
    }
}
