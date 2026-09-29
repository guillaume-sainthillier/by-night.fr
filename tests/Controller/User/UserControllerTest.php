<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\User;

use App\Entity\User;
use App\Factory\CityFactory;
use App\Factory\EventFactory;
use App\Factory\PlaceFactory;
use App\Factory\TagFactory;
use App\Factory\UserEventFactory;
use App\Factory\UserFactory;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    public function testAMemberWithoutAnyEventIsNotIndexable(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'lurker']);

        $client->request('GET', $this->profilePath($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="robots"][content="noindex, follow"]');
    }

    public function testAMemberWithEventsInTheirCalendarIsIndexable(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'active']);
        UserEventFactory::createOne(['user' => $user]);

        $client->request('GET', $this->profilePath($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('meta[name="robots"]');
    }

    public function testTheProfileAgreesTheFavoritesCountWithItsNoun(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'fan']);
        UserEventFactory::createMany(2, ['user' => $user]);

        $client->request('GET', $this->profilePath($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('meta[name="description"][content*="2 sorties en favoris"]');
    }

    public function testTheProfileShowsWhereAndWhenTheMemberGoesOut(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'noctambule']);
        $toulouse = CityFactory::toulouse()->create();
        $bikini = PlaceFactory::createOne(['name' => 'Le Bikini', 'city' => $toulouse, 'country' => $toulouse->getCountry()]);
        // Two Fridays of May
        $concert = TagFactory::createOne(['name' => 'Concert']);
        foreach (['2024-05-03', '2025-05-02'] as $day) {
            $event = EventFactory::new()->withDates(new DateTimeImmutable($day))->create(['place' => $bikini, 'category' => $concert]);
            UserEventFactory::createOne(['user' => $user, 'event' => $event]);
        }

        $client->request('GET', $this->profilePath($user));

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('h2', 'Villes & lieux les plus fréquentés');
        self::assertAnySelectorTextContains('a', '1. Toulouse');
        self::assertAnySelectorTextContains('a.stretched-link', 'Le Bikini');
        self::assertAnySelectorTextContains('.form-panel', "Concert 2 sorties (100\u{a0}%)");
        self::assertAnySelectorTextContains('.badge', 'Pic en mai');
        self::assertAnySelectorTextContains('.card-body', "100\u{a0}% des sorties tombent le vendredi");
    }

    public function testAMemberWithoutEventsHasNoStatistics(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['username' => 'lurker']);

        $client->request('GET', $this->profilePath($user));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Villes & lieux les plus fréquentés');
        self::assertSelectorTextNotContains('body', 'Activité & rythme des sorties');
        self::assertSelectorTextNotContains('body', 'Top catégories & univers préférés');
    }

    public function testAContributorIsAMemberWhoPublishedAnEvent(): void
    {
        $client = self::createClient();
        $contributor = UserFactory::createOne(['username' => 'organisateur']);
        EventFactory::createOne(['user' => $contributor]);
        $drafter = UserFactory::createOne(['username' => 'brouillon']);
        EventFactory::createOne(['user' => $drafter, 'draft' => true]);

        $client->request('GET', $this->profilePath($contributor));
        self::assertAnySelectorTextContains('.badge', 'Contributeur Pro');

        $client->request('GET', $this->profilePath($drafter));
        self::assertSelectorTextNotContains('body', 'Contributeur Pro');
    }

    private function profilePath(User $user): string
    {
        return \sprintf('/membres/%s--%d', $user->getSlug(), $user->getId());
    }
}
