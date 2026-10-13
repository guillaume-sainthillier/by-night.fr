<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\EventSubscriber;

use App\Factory\CityFactory;
use App\Factory\CountryFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * The URLs of the countries carried a "c--" prefix: each former one reaches the new one in a single redirect.
 */
final class LegacyCountryUrlSubscriberTest extends AppWebTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFormerUrls(): iterable
    {
        yield 'country page' => ['/c--france', '/france'];
        yield 'with its trailing slash' => ['/c--france/', '/france'];
        yield 'former agenda' => ['/c--france/agenda', '/france'];
        yield 'former agenda page, with its filters' => ['/c--france/agenda/2?when=this_weekend&price=free', '/france/2?when=this_weekend&price=free'];
        yield 'type page' => ['/c--suisse/agenda/sortir/concert', '/suisse/agenda/sortir/concert'];
        yield 'event page' => ['/c--france/soiree/fete-des-sardines--2683463', '/france/soiree/fete-des-sardines--2683463'];
    }

    #[DataProvider('provideFormerUrls')]
    public function testAFormerUrlRedirectsOnceToTheNewOne(string $formerUrl, string $url): void
    {
        $client = self::createClient();

        $client->request('GET', $formerUrl);

        self::assertResponseRedirects($url, Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testTheFormerUrlOfADuplicateLeadsToItsCanonicalEventAtOnce(): void
    {
        $client = self::createClient();
        $france = CountryFactory::france()->create();
        $place = PlaceFactory::createOne(['country' => $france, 'city' => null]);
        $canonical = EventFactory::createOne(['name' => 'Visite de la ferme', 'place' => $place]);
        $duplicate = EventFactory::createOne(['name' => 'Visite de la ferme', 'place' => $place, 'duplicateOf' => $canonical]);
        $canonicalPath = \sprintf('/france/soiree/%s--%d', $canonical->getSlug(), $canonical->getId());

        $client->request('GET', \sprintf('/c--france/soiree/%s--%d?utm_source=x', $duplicate->getSlug(), $duplicate->getId()));
        self::assertResponseRedirects($canonicalPath . '?utm_source=x', Response::HTTP_MOVED_PERMANENTLY);

        // The pages of its widgets keep their page
        $client->request('GET', \sprintf('/c--france/soiree/%s--%d/prochaines-soirees/2', $duplicate->getSlug(), $duplicate->getId()));
        self::assertResponseRedirects($canonicalPath . '/prochaines-soirees/2', Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testTheFormerUrlOfAnEventGivenACitySinceLeadsToItsCity(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $event = EventFactory::createOne(['place' => PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()])]);

        $client->request('GET', \sprintf('/c--france/soiree/%s--%d', $event->getSlug(), $event->getId()));

        self::assertResponseRedirects(\sprintf('/toulouse/soiree/%s--%d', $event->getSlug(), $event->getId()), Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testTheFormerUrlOfAHiddenDraftTellsNothingOfIt(): void
    {
        $client = self::createClient();
        $toulouse = CityFactory::toulouse()->create();
        $draft = EventFactory::createOne([
            'place' => PlaceFactory::createOne(['city' => $toulouse, 'country' => $toulouse->getCountry()]),
            'user' => UserFactory::createOne(),
            'draft' => true,
        ]);
        $formerPath = \sprintf('/soiree/%s--%d', $draft->getSlug(), $draft->getId());

        $client->request('GET', '/c--france' . $formerPath);

        self::assertResponseRedirects('/france' . $formerPath, Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testACitySlugStartingLikeACountryOneIsNoFormerUrl(): void
    {
        $client = self::createClient();

        $client->request('GET', '/c-france');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
