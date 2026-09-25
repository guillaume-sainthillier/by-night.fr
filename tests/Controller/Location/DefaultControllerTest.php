<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\Location;

use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DefaultControllerTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLocationPages(): iterable
    {
        yield 'country' => ['/c--france/', 'en France'];
        yield 'city' => ['/toulouse/', 'à Toulouse'];
    }

    #[DataProvider('provideLocationPages')]
    public function testTheLocationPageListsItsPublishedUpcomingEventsSoonestAndMostPopularFirst(string $url, string $atName): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]);
        $belgium = CountryFactory::belgium()->create();
        $brussels = CityFactory::createOne(['name' => 'Bruxelles', 'country' => $belgium]);
        $belgianPlace = PlaceFactory::createOne(['city' => $brussels, 'country' => $belgium]);
        $tomorrow = new DateTimeImmutable('tomorrow');
        $inTwoDays = new DateTimeImmutable('+2 days');

        $popular = EventFactory::new()->withDates($tomorrow)->create(['name' => 'Popular tomorrow', 'place' => $place, 'participations' => 50]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Quiet tomorrow', 'place' => $place, 'participations' => 5]);
        EventFactory::new()->withDates($inTwoDays)->create(['name' => 'In two days', 'place' => $place, 'participations' => 500]);
        EventFactory::new()->withDates(new DateTimeImmutable('-10 days'))->create(['name' => 'Past', 'place' => $place]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Draft', 'place' => $place, 'draft' => true]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Duplicate', 'place' => $place, 'duplicateOf' => $popular]);
        EventFactory::new()->withDates($tomorrow)->create(['name' => 'Brussels', 'place' => $belgianPlace]);

        $crawler = $client->request('GET', $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.footer_wrapper h2', '3 événements et bons plans culturels ' . $atName);
        self::assertSame(
            ['Popular tomorrow', 'Quiet tomorrow', 'In two days'],
            $crawler->filter('.card-event h3')->each(static fn ($title): string => trim($title->text())),
        );
    }
}
