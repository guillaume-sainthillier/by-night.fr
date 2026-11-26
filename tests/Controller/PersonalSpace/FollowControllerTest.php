<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Factory\CityFactory;
use App\Factory\FollowFactory;
use App\Factory\PlaceFactory;
use App\Factory\PublisherFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;

/**
 * "Mes abonnements": the publishers and the venues a member follows.
 */
final class FollowControllerTest extends AppWebTestCase
{
    public function testThePageListsWhatTheMemberFollows(): void
    {
        $client = self::createClient();
        $member = UserFactory::createOne();
        $toulouse = CityFactory::toulouse()->create();
        FollowFactory::createOne(['user' => $member, 'publisher' => PublisherFactory::createOne(['name' => 'Le Collectif'])]);
        FollowFactory::createOne(['user' => $member, 'publisher' => null, 'place' => PlaceFactory::createOne(['name' => 'Le Bikini', 'slug' => 'le-bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()])]);
        FollowFactory::createOne(['publisher' => PublisherFactory::createOne(['name' => 'Pas le mien'])]);
        $client->loginUser($member);

        $crawler = $client->request('GET', '/espace-perso/abonnements');

        self::assertResponseIsSuccessful();
        $names = $crawler->filter('#follows a.fw-bold')->each(static fn ($link): string => trim($link->text()));
        sort($names);
        self::assertSame(['Le Bikini', 'Le Collectif'], $names);
        self::assertSelectorExists('#follows a[href="/toulouse/agenda/sortir-a/le-bikini"]');
        self::assertCount(2, $crawler->filter('#follows .btn-follow.btn-primary'));
    }

    public function testAMemberWhoFollowsNoOneIsToldHow(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', '/espace-perso/abonnements');

        self::assertSelectorTextContains('.empty-title', 'Vous ne suivez personne');
    }

    public function testThePageNeedsAnAccount(): void
    {
        $client = self::createClient();

        $client->request('GET', '/espace-perso/abonnements');

        self::assertResponseRedirects('/login');
    }
}
