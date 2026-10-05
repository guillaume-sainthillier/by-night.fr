<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Routing;

use App\Enum\AgendaType;
use App\Factory\CityFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Routing\AgendaUrlGenerator;
use App\Tests\AppKernelTestCase;

final class AgendaUrlGeneratorTest extends AppKernelTestCase
{
    /**
     * The venue names the path, else the category, else the type; the others and the filters come in the query string.
     */
    public function testTheMostSpecificFilterNamesThePath(): void
    {
        $generator = self::getContainer()->get(AgendaUrlGenerator::class);
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $jazz = TagFactory::createOne(['name' => 'Jazz']);
        $filters = ['when' => 'this_weekend'];

        self::assertSame(
            \sprintf('/toulouse/agenda/sortir-a/le-bikini?when=this_weekend&type=student&tag=%d', $jazz->getId()),
            $generator->generate('toulouse', AgendaType::Student, $bikini, $jazz, $filters),
        );
        self::assertSame(
            \sprintf('/toulouse/agenda/tag/jazz--%d?when=this_weekend&type=student', $jazz->getId()),
            $generator->generate('toulouse', AgendaType::Student, null, $jazz, $filters),
        );
        self::assertSame('/toulouse/agenda/sortir/etudiant?when=this_weekend', $generator->generate('toulouse', AgendaType::Student, filters: $filters));
        self::assertSame('/toulouse?when=this_weekend', $generator->generate('toulouse', filters: $filters));
    }

    public function testAVenueKeepsItsOwnLocation(): void
    {
        $generator = self::getContainer()->get(AgendaUrlGenerator::class);
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        self::assertSame('/toulouse/agenda/sortir-a/le-bikini', $generator->generate('france', place: $bikini));
    }
}
