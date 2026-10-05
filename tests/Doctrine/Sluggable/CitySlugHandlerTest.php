<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Doctrine\Sluggable;

use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Tests\AppKernelTestCase;

use function Zenstruck\Foundry\Persistence\flush_after;

final class CitySlugHandlerTest extends AppKernelTestCase
{
    public function testACityNeverTakesTheSlugOfACountry(): void
    {
        CountryFactory::switzerland()->create();
        $france = CountryFactory::france()->create();

        $suisse = CityFactory::createOne(['name' => 'Suisse', 'country' => $france]);

        self::assertSame('suisse-1', $suisse->getSlug());
    }

    public function testTheCitiesOfOneFlushTakeDifferentSuffixes(): void
    {
        CountryFactory::switzerland()->create();
        $france = CountryFactory::france()->create();

        [$first, $second] = flush_after(static fn (): array => CityFactory::createMany(2, ['name' => 'Suisse', 'country' => $france]));

        self::assertSame(['suisse-1', 'suisse-2'], [$first->getSlug(), $second->getSlug()]);
    }

    public function testACityOfAnotherNameKeepsItsSlug(): void
    {
        CountryFactory::switzerland()->create();

        self::assertSame('toulouse', CityFactory::toulouse()->create()->getSlug());
    }

    public function testACityOfACountryPrefixingItsCitiesStartsWithTheCountry(): void
    {
        $switzerland = CountryFactory::switzerland()->create();
        $france = CountryFactory::france()->create();

        self::assertSame('suisse/geneve', CityFactory::createOne(['name' => 'Genève', 'country' => $switzerland])->getSlug());
        // Unique within its country only
        self::assertSame('suisse/geneve-1', CityFactory::createOne(['name' => 'Genève', 'country' => $switzerland])->getSlug());
        self::assertSame('geneve', CityFactory::createOne(['name' => 'Genève', 'country' => $france])->getSlug());
    }

    public function testACityUnderItsCountryNeverEndsWithAWordOfTheRoutes(): void
    {
        $switzerland = CountryFactory::switzerland()->create();

        // "/suisse/agenda" and "/suisse/2" are pages of the country
        self::assertSame('suisse/agenda-1', CityFactory::createOne(['name' => 'Agenda', 'country' => $switzerland])->getSlug());
        self::assertSame('suisse/2-1', CityFactory::createOne(['name' => '2', 'country' => $switzerland])->getSlug());
    }
}
