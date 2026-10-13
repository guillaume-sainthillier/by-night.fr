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
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use DateTime;

final class WidgetsControllerTest extends AppWebTestCase
{
    public function testWidgetFragmentsTellSearchEnginesNotToIndexThem(): void
    {
        $client = self::createClient();
        $city = CityFactory::toulouse()->create();
        $event = EventFactory::createOne([
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
        ]);
        $eventPath = \sprintf('/%s/soiree/%s--%d', $event->getLocationSlug(), $event->getSlug(), $event->getId());

        $fragments = [
            $eventPath . '/prochaines-soirees/1',
            $eventPath . '/autres-soirees/1',
        ];

        foreach ($fragments as $fragment) {
            $client->request('GET', $fragment);

            self::assertResponseIsSuccessful($fragment);
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex', $fragment);
        }
    }

    public function testTheNextPagesAreCardsInAjaxAndAPageOfTheirOwnOtherwise(): void
    {
        $client = self::createClient();
        $city = CityFactory::toulouse()->create();
        $place = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $city, 'country' => $city->getCountry()]);
        $event = EventFactory::createOne(['name' => 'Concert du soir', 'place' => $place, 'startDate' => new DateTime('+1 day'), 'endDate' => new DateTime('+1 day')]);
        // Two pages of the venue's next events (8 per page)
        EventFactory::createMany(9, ['place' => $place, 'startDate' => new DateTime('+2 days'), 'endDate' => new DateTime('+2 days')]);
        $nextPage = \sprintf('/%s/soiree/%s--%d/prochaines-soirees/2', $event->getLocationSlug(), $event->getSlug(), $event->getId());

        $crawler = $client->request('GET', $nextPage);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Vary', 'X-Requested-With');
        self::assertSelectorTextContains('h1', 'Prochainement');
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
        self::assertCount(1, $crawler->filter('.scroll-item'), 'The ninth event, alone on the second page');
        self::assertSelectorExists(\sprintf('a[rel="nofollow"][href$="--%d/prochaines-soirees"]', $event->getId()));
        self::assertSelectorExists(\sprintf('a[href$="/soiree/%s--%d"]', $event->getSlug(), $event->getId()));

        $client->xmlHttpRequest('GET', $nextPage);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertStringNotContainsString('<html', (string) $client->getResponse()->getContent());
        self::assertSelectorNotExists('h1');
        self::assertSelectorExists('.scroll-item');
    }

    public function testTheWidgetsOfADraftAreOnlyForThoseWhoCanSeeIt(): void
    {
        $client = self::createClient();
        $city = CityFactory::toulouse()->create();
        $author = UserFactory::createOne();
        $draft = EventFactory::createOne([
            'place' => PlaceFactory::createOne(['city' => $city, 'country' => $city->getCountry()]),
            'user' => $author,
            'draft' => true,
        ]);
        $draftPath = \sprintf('/%s/soiree/%s--%d', $draft->getLocationSlug(), $draft->getSlug(), $draft->getId());
        $fragments = [$draftPath . '/prochaines-soirees/1', $draftPath . '/autres-soirees/1'];

        foreach ($fragments as $fragment) {
            $client->request('GET', $fragment);

            self::assertResponseStatusCodeSame(404, $fragment);
        }

        $client->loginUser($author);
        foreach ($fragments as $fragment) {
            $client->request('GET', $fragment);

            self::assertResponseIsSuccessful($fragment);
        }
    }
}
