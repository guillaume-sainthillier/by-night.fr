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
