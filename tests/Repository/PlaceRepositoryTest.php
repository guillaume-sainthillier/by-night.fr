<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Repository;

use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\PlaceFactory;
use App\Repository\PlaceRepository;
use App\Tests\AppKernelTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

class PlaceRepositoryTest extends AppKernelTestCase
{
    use ResetDatabase;

    private ?PlaceRepository $repository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get(PlaceRepository::class);
    }

    public function testFindReturnsPlace(): void
    {
        $place = PlaceFactory::createOne();

        $found = $this->repository->find($place->getId());

        self::assertInstanceOf(Place::class, $found);
        self::assertEquals($place->getId(), $found->getId());
    }

    public function testFindByName(): void
    {
        $france = CountryFactory::france()->create();
        $toulouse = CityFactory::toulouse()->create();

        PlaceFactory::createOne([
            'name' => 'Le Bikini',
            'city' => $toulouse,
            'country' => $france,
        ]);

        PlaceFactory::createOne([
            'name' => 'Le Dynamo',
            'city' => $toulouse,
            'country' => $france,
        ]);

        $results = $this->repository->findBy(['name' => 'Le Bikini']);

        self::assertCount(1, $results);
        self::assertEquals('Le Bikini', $results[0]->getName());
    }

    public function testFindByCity(): void
    {
        $france = CountryFactory::france()->create();
        $toulouse = CityFactory::toulouse()->create();
        $paris = CityFactory::new(['name' => 'Paris', 'country' => $france])->create();

        PlaceFactory::createMany(3, ['city' => $toulouse, 'country' => $france]);
        PlaceFactory::createMany(2, ['city' => $paris, 'country' => $france]);

        $results = $this->repository->findBy(['city' => $toulouse->object()]);

        self::assertCount(3, $results);
    }

    public function testFindAllReturnsAllPlaces(): void
    {
        PlaceFactory::createMany(5);

        $results = $this->repository->findAll();

        self::assertCount(5, $results);
    }
}
