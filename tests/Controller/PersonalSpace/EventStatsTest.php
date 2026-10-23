<?php

/*
 * This file is part of By Night.
 * (c) 2013-present Guillaume Sainthillier <guillaume.sainthillier@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace App\Tests\Controller\PersonalSpace;

use App\Factory\EventFactory;
use App\Factory\EventVisitStatFactory;
use App\Factory\UserFactory;
use App\Tests\AppWebTestCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

final class EventStatsTest extends AppWebTestCase
{
    public function testTheAuthorSeesTheVisitorsAndTicketingClicksOfTheirEvent(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne(['name' => 'Concert de jazz']);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('today'), 'visitors' => 12, 'ticketClicks' => 3]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-10 days'), 'visitors' => 30, 'ticketClicks' => 1]);
        // Over before the 30 days: only in the total
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-60 days'), 'visitors' => 100, 'ticketClicks' => 9]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d/statistiques', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Concert de jazz');
        $tiles = $crawler->filter('#event-stats .row-cards .h1')->each(static fn ($node): string => $node->text());
        self::assertSame(['12', '42', '142', '4'], $tiles);
        self::assertCount(30, $crawler->filter('#event-stats details tbody tr'));
    }

    public function testAnEventWithoutVisitsSaysSo(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $client->loginUser($event->getUser());

        $client->request('GET', \sprintf('/espace-perso/%d/statistiques', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#event-stats .empty-title', 'Pas encore de visite');
        self::assertSelectorNotExists('#event-stats details');
        self::assertSelectorNotExists('#event-stats-google-analytics');
    }

    public function testTheDaysReadFromGoogleAnalyticsAreSaidToCountFewerVisitors(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-3 days'), 'visitors' => 4, 'ticketClicks' => 0, 'fromGoogleAnalytics' => true]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-2 days'), 'visitors' => 10, 'ticketClicks' => 1]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d/statistiques', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#event-stats-google-analytics', 'Google Analytics');
        // Every day up to the last one read from Google Analytics is paler, not the last two days and today
        self::assertCount(27, $crawler->filter('#event-stats .bg-primary.opacity-50'));
        self::assertCount(30, $crawler->filter('#event-stats .bg-primary.rounded-top'));
    }

    public function testAnotherMemberCannotSeeThem(): void
    {
        $client = self::createClient();
        $event = EventFactory::createOne();
        $client->loginUser(UserFactory::createOne());

        $client->request('GET', \sprintf('/espace-perso/%d/statistiques', $event->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testADuplicateShowsTheAudienceOfThePageItRedirectsTo(): void
    {
        $client = self::createClient();
        $canonical = EventFactory::createOne();
        $event = EventFactory::createOne(['duplicateOf' => $canonical]);
        EventVisitStatFactory::createOne(['event' => $canonical, 'day' => new DateTimeImmutable('today'), 'visitors' => 7, 'ticketClicks' => 2]);
        $client->loginUser($event->getUser());

        $crawler = $client->request('GET', \sprintf('/espace-perso/%d/statistiques', $event->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame('7', $crawler->filter('#event-stats .row-cards .h1')->first()->text());
    }

    public function testTheListShowsTheAudienceOfTheLast30Days(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $event = EventFactory::createOne(['user' => $user]);
        $quiet = EventFactory::createOne(['user' => $user]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('yesterday'), 'visitors' => 21, 'ticketClicks' => 1]);
        EventVisitStatFactory::createOne(['event' => $event, 'day' => new DateTimeImmutable('-45 days'), 'visitors' => 500, 'ticketClicks' => 50]);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/espace-perso/mes-soirees');

        self::assertResponseIsSuccessful();
        $cell = $crawler->filter(\sprintf('a[href="/espace-perso/%d/statistiques"].text-reset', $event->getId()));
        self::assertSame('21 visiteurs 1 clic billetterie', $cell->text());
        self::assertSame('0 visiteur', $crawler->filter(\sprintf('a[href="/espace-perso/%d/statistiques"].text-reset', $quiet->getId()))->text());
    }
}
