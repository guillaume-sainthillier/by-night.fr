<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Command;

use App\App\Location;
use App\Entity\City;
use App\Entity\Place;
use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\PlaceLegacySlugFactory;
use App\Factory\PlaceMetadataFactory;
use App\Factory\PlaceNameSlugFactory;
use App\Repository\PlaceRepository;
use App\Tests\AppKernelTestCase;
use Override;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The places of a city recorded under the same name and street become one, the one with the most events; its
 * namesakes on another street or in another city stay.
 */
final class PlacesMergeDuplicatesCommandTest extends AppKernelTestCase
{
    private City $paris;

    private Place $first;

    private Place $busiest;

    private Place $otherStreet;

    private Place $otherCity;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $country = CountryFactory::createOne(['id' => 'FR', 'name' => 'France']);
        $this->paris = CityFactory::createOne(['name' => 'Paris', 'country' => $country]);
        $lyon = CityFactory::createOne(['name' => 'Lyon', 'country' => $country]);
        $inParis = ['city' => $this->paris, 'country' => $country];

        // The same gallery three times: the case, the accents and the punctuation aside
        $this->first = PlaceFactory::createOne(['name' => 'Galerie Brugier Rigail', 'street' => '40 Rue Volta', 'slug' => 'galerie-brugier-rigail', ...$inParis]);
        $this->busiest = PlaceFactory::createOne(['name' => 'GALERIE BRUGIER-RIGAIL', 'street' => '40, rue Volta', 'slug' => 'galerie-brugier-rigail-1', ...$inParis]);
        PlaceFactory::createOne(['name' => 'Galerie Brugier Rigail', 'street' => '40 rue volta', 'slug' => 'galerie-brugier-rigail-1', ...$inParis]);
        EventFactory::createOne(['place' => $this->first]);
        EventFactory::createMany(2, ['place' => $this->busiest]);
        PlaceMetadataFactory::createOne(['place' => $this->first, 'externalId' => '312957758868438', 'externalOrigin' => 'facebook']);
        PlaceNameSlugFactory::createOne(['place' => $this->first, 'slug' => 'galerie brugier rigail', 'city' => $this->paris, 'country' => $country]);
        // A slug of a place merged into the first one before
        PlaceLegacySlugFactory::createOne(['place' => $this->first, 'slug' => 'brugier-rigail']);

        // Namesakes elsewhere
        $this->otherStreet = PlaceFactory::createOne(['name' => 'Galerie Brugier Rigail', 'street' => '12 Rue Volta', ...$inParis]);
        $this->otherCity = PlaceFactory::createOne(['name' => 'Galerie Brugier Rigail', 'street' => '40 Rue Volta', 'city' => $lyon, 'country' => $country]);
        EventFactory::createOne(['place' => $this->otherStreet]);
    }

    public function testPreviewCountsWithoutMerging(): void
    {
        $display = $this->doRunCommand([])->getDisplay();

        self::assertStringContainsString('Previewing the duplicate places of 1 cities', $display);
        self::assertMatchesRegularExpression('/\s1\s+2\s+1\s+1\s/', $display, 'groups, places removed, events moved, slugs kept');
        self::assertStringContainsString('Preview only', $display);
        self::assertSame(5, PlaceFactory::count());
        self::assertSame(1, EventFactory::count(['place' => $this->first]));
    }

    public function testApplyMergesIntoThePlaceWithTheMostEvents(): void
    {
        $this->doRunCommand(['--apply' => true]);

        self::assertSame(
            [$this->busiest->getId(), $this->otherStreet->getId(), $this->otherCity->getId()],
            array_map(static fn (Place $place): ?int => $place->getId(), PlaceFactory::all()),
        );
        self::assertSame(3, EventFactory::count(['place' => $this->busiest]));
        self::assertSame($this->busiest->getId(), PlaceMetadataFactory::find(['externalId' => '312957758868438'])->getPlace()?->getId());
        self::assertSame(1, PlaceNameSlugFactory::count(['place' => $this->busiest]));
    }

    public function testTheKeptPlaceTakesTheShortestSlugAndTheOthersLeadToIt(): void
    {
        $this->doRunCommand(['--apply' => true]);

        self::assertSame('galerie-brugier-rigail', PlaceFactory::find(['id' => $this->busiest->getId()])->getSlug());
        $repository = self::getContainer()->get(PlaceRepository::class);
        $inParis = new Location()->setCity($this->paris);
        // Its own former slug (the event-less place's too), and the one of a place merged into the first one before
        self::assertSame($this->busiest->getId(), $repository->findOneByLegacySlug('galerie-brugier-rigail-1', $inParis)?->getId());
        self::assertSame($this->busiest->getId(), $repository->findOneByLegacySlug('brugier-rigail', $inParis)?->getId());
        self::assertSame(2, PlaceLegacySlugFactory::count());
    }

    public function testACityCanBeMergedAlone(): void
    {
        $display = $this->doRunCommand(['--city' => (string) $this->otherCity->getCity()?->getId()])->getDisplay();

        self::assertMatchesRegularExpression('/\s0\s+0\s+0\s+0\s/', $display);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function doRunCommand(array $input): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:places:merge-duplicates'));
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        return $tester;
    }
}
