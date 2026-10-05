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
use Symfony\Component\HttpFoundation\Request;

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

    /**
     * A GET form drops the query string of its action: what route() puts there comes as hidden fields.
     */
    public function testTheFormTargetsThePathAndCarriesTheQueryAsFields(): void
    {
        $generator = self::getContainer()->get(AgendaUrlGenerator::class);
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $jazz = TagFactory::createOne(['name' => 'Jazz']);

        self::assertSame(
            ['/toulouse/agenda/sortir-a/le-bikini', ['type' => 'student', 'tag' => $jazz->getId()]],
            $generator->formTarget('toulouse', AgendaType::Student, $bikini, $jazz),
        );
        self::assertSame(['/toulouse/agenda/sortir-a/le-bikini', []], $generator->formTarget('toulouse', place: $bikini));
        self::assertSame(
            [\sprintf('/toulouse/agenda/tag/jazz--%d', $jazz->getId()), ['type' => 'student']],
            $generator->formTarget('toulouse', AgendaType::Student, tag: $jazz),
        );
        self::assertSame(['/toulouse/agenda/sortir/etudiant', []], $generator->formTarget('toulouse', AgendaType::Student));
    }

    /**
     * Read back, the query string of a URL gives the filters generate() put there, whatever the path names.
     */
    public function testTheQueryStringNarrowsAVenueOrACategoryPage(): void
    {
        $generator = self::getContainer()->get(AgendaUrlGenerator::class);
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $jazz = TagFactory::createOne(['name' => 'Jazz']);
        $request = Request::create($generator->generate('toulouse', AgendaType::Student, $bikini, $jazz));

        [$type, $tag] = $generator->resolveQuery($request, place: $bikini);
        self::assertSame(AgendaType::Student, $type);
        self::assertSame($jazz->getId(), $tag?->getId());

        // A category page keeps its own category: "tag" only narrows a venue page
        $rock = TagFactory::createOne(['name' => 'Rock']);
        self::assertSame([AgendaType::Student, $rock], $generator->resolveQuery($request, tag: $rock));

        // A type page names its type in its path, the index none: their query string narrows nothing
        self::assertSame([AgendaType::Concert, null], $generator->resolveQuery($request, AgendaType::Concert));
        self::assertSame([null, null], $generator->resolveQuery($request));
    }

    public function testAnUnknownTypeOrCategoryInTheQueryStringIsLeftOut(): void
    {
        $generator = self::getContainer()->get(AgendaUrlGenerator::class);
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        self::assertSame([null, null], $generator->resolveQuery(Request::create('/?type=nope&tag=999999'), place: $bikini));
    }

    public function testAVenueKeepsItsOwnLocation(): void
    {
        $generator = self::getContainer()->get(AgendaUrlGenerator::class);
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);

        self::assertSame('/toulouse/agenda/sortir-a/le-bikini', $generator->generate('france', place: $bikini));
    }
}
