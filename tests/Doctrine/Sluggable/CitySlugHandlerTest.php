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
}
